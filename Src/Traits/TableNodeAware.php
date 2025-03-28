<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits;

use Closure;
use DOMNode;
use Iterator;
use DOMElement;
use ArrayObject;
use DOMNodeList;
use SplFixedArray;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\AssertDOMElement;
use TheWebSolver\Codegarage\Scraper\Data\CollectionSet;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Marshaller\TableColumnMarshaller;

/**
 * @template ThReturn
 * @template TdReturn
 */
trait TableNodeAware {
	/** @placeholder `1:` static classname, `2:` throwing methodname, `3:` reason. */
	final public const USE_EVENT_DISPATCHER = 'Calling "%1$s::%2$s()" is not allowed before table is discovered. Use listener passed to "%1$s::subscribeWith()" to %3$s';

	private bool $shouldPerform__allTableDiscovery = false;

	/**
	 * @var array{
	 *   tr ?: Transformer<CollectionSet<TdReturn>|iterable<int,string|DOMNode>>,
	 *   th ?: Transformer<ThReturn>,
	 *   td ?: Transformer<TdReturn>
	 * }
	 */
	private array $discoveredTable__transformers;
	/** @var array<string,Closure( static ): void> */
	private array $discoveredTable__eventListeners;
	/** @var int[] */
	private array $discoveredTable__bodyIds = array();
	/** @var array<int,ArrayObject<int,ThReturn>> */
	private array $discoveredTable__heads = array();
	/** @var array<int,SplFixedArray<string>> */
	private array $discoveredTable__headNames = array();
	/** @var array<int,Iterator<array-key,ArrayObject<array-key,TdReturn>>> */
	private array $discoveredTable__rows = array();

	private int $currentTable__splId;
	private int $currentTable__bodyId;
	/** @var array<int,list<string>> */
	private array $currentTable__columnNames;
	/** @var array<int,int> */
	private array $currentTable__lastColumn = array();

	private int $currentIteration__headCount;
	private int $currentIteration__headIndex;
	private string $currentIteration__columnIndex;
	/** @var array<int,int> */
	private array $currentIteration__columnCount = array();

	public function setColumnNames( array $keys, int $id ): void {

		( $id && $this->getTableId( current: true ) === $id ) || throw new ScraperError(
			sprintf( self::USE_EVENT_DISPATCHER, static::class, __FUNCTION__, 'set column names.' )
		);

		if ( ! $keys ) {
			return;
		}

		$this->currentTable__columnNames[ $id ] = $keys;
		$this->currentTable__lastColumn[ $id ]  = array_key_last( $keys );
	}

	public function withTransformers( array $transformers ): static {
		isset( $transformers['tr'] ) && ( $this->discoveredTable__transformers['tr'] = $transformers['tr'] );
		isset( $transformers['th'] ) && ( $this->discoveredTable__transformers['th'] = $transformers['th'] );
		isset( $transformers['td'] ) && ( $this->discoveredTable__transformers['td'] = $transformers['td'] );

		return $this;
	}

	/** @return ($current is true ? int : int[]) */
	public function getTableId( bool $current = false ): int|array {
		return $current ? $this->currentTable__bodyId ?? 0 : $this->discoveredTable__bodyIds;
	}

	/** @return list<string> */
	public function getColumnNames(): array {
		return $this->currentTable__columnNames[ $this->currentTable__bodyId ] ?? array();
	}

	public function getCurrentColumnName(): ?string {
		return $this->currentIteration__columnIndex ?? null;
	}

	public function getCurrentIterationCountOf( Table $element ): ?int {
		return match ( $element ) {
			default       => null,
			Table::Head   => $this->currentIteration__headCount ?? null,
			Table::Column => $this->currentIteration__columnCount[ $this->currentTable__bodyId ] ?? null,
		};
	}

	/** @return ($namesOnly is true ? array<int,SplFixedArray<string>> : array<int,ArrayObject<int,ThReturn>>) */
	public function getTableHead( bool $namesOnly = false ): array {
		return $namesOnly ? $this->discoveredTable__headNames : $this->discoveredTable__heads;
	}

	/** @return array<int,Iterator<array-key,ArrayObject<array-key,TdReturn>>> */
	public function getTableData(): array {
		return $this->discoveredTable__rows;
	}

	public function withAllTables( bool $trace = true ): static {
		$this->shouldPerform__allTableDiscovery = $trace;

		return $this;
	}

	public function traceTableIn( iterable $elementList ): void {
		$elementList instanceof DOMNodeList || throw new InvalidSource(
			sprintf( 'Table Node tracer only accepts "%1$s".', DOMNodeList::class )
		);

		foreach ( $elementList as $node ) {
			if ( ! $tableElements = $this->discoveredStructureIn( $node ) ) {
				continue;
			}

			$splId = $this->currentTable__splId = spl_object_id( $node );
			$id    = $splId * spl_object_id( $tableElements[1] );

			$this->dispatchEventListenerForDiscoveredTable( $id );

			$iterator = $this->fromTableBodyRowStructure( ...$tableElements );

			$iterator->valid() && ( $this->discoveredTable__rows[ $id ] = $iterator );

			if ( $this->discoveredTargetedTable( $node ) ) {
				break;
			}
		}
	}

	/** @return ?array{0:list<string>,1:list<ThReturn>} */
	public function inferTableHeadFrom( iterable $elementList ): ?array {
		$thTransformer = $this->discoveredTable__transformers['th'] ?? null;
		$names         = $collection = array();
		$skippedNodes  = 0;

		foreach ( $elementList as $currentIndex => $headNode ) {
			if ( ! AssertDOMElement::isValid( $headNode, type: 'th' ) ) {
				++$skippedNodes;

				continue;
			}

			$position = $currentIndex - $skippedNodes;

			$this->registerCurrentIterationTH( $position );

			$trimmed      = trim( $headNode->textContent );
			$content      = $thTransformer?->transform( $headNode, $position, $this ) ?? $trimmed;
			$names[]      = is_string( $content ) ? $content : $trimmed;
			$collection[] = $content;
		}

		return $collection ? array( $names, $collection ) : null;
	}

	public function inferTableDataFrom( iterable $elementList ): array {
		$data     = array();
		$keys     = $this->getColumnNames();
		$last     = $this->currentTable__lastColumn[ $this->currentTable__bodyId ] ?? null;
		$position = $skippedNodes = $this->currentIteration__columnCount[ $this->currentTable__bodyId ] = 0;

		/** @var Transformer<TdReturn> Marshaller's TReturn is always string. */
		$transformer = $this->discoveredTable__transformers['td'] ?? new TableColumnMarshaller();

		foreach ( $elementList as $currentIndex => $node ) {
			if ( ! $this->isTHorTDStructure( $node ) ) {
				++$skippedNodes;

				continue;
			}

			$position = $currentIndex - $skippedNodes;

			if ( $last && $position > $last ) {
				break;
			}

			$indexKey = $keys[ $position ] ?? null;

			$this->registerCurrentIterationTD( $indexKey, count: $position + 1 );

			$this->collectedTDFrom( $node, $transformer, $data )
				&& $this->findTableStructureIn( $node, minChildNodesCount: 1 );
		}

		unset( $this->currentIteration__columnIndex );

		return $data;
	}

	protected function flushTransformers(): void {
		unset( $this->discoveredTable__transformers );
	}

	protected function flushDiscoveredContents(): void {
		unset(
			$this->discoveredTable__eventListeners,
			$this->discoveredTable__heads,
			$this->discoveredTable__headNames,
			$this->discoveredTable__rows,
		);
	}

	/** @param callable( static ): void $eventListener */
	public function subscribeWith( callable $eventListener, Table $target = Table::Row ): static {
		$this->discoveredTable__eventListeners[ $target->name ] = $eventListener( ... );

		return $this;
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- To be used by exhibiting class.
	protected function isTargetedTable( DOMElement $node ): bool {
		return true;
	}

	final protected function dispatchEventListenerForDiscoveredTable( int $id ): void {
		if ( ! in_array( $id, $this->discoveredTable__bodyIds, true ) ) {
			$this->discoveredTable__bodyIds[] = $this->currentTable__bodyId = $id;
		}

		isset( $this->discoveredTable__eventListeners[ Table::Row->name ] )
			&& ( $this->discoveredTable__eventListeners[ Table::Row->name ] )( $this );

		unset( $this->discoveredTable__eventListeners[ Table::Row->name ] );
	}

	final protected function findTableStructureIn( DOMNode $node, int $minChildNodesCount = 0 ): void {
		( ! $this->getTableId() || $this->shouldPerform__allTableDiscovery )
			&& ( ( $nodes = $node->childNodes )->length > $minChildNodesCount )
			&& $this->traceTableIn( $nodes );
	}

	/** @phpstan-assert-if-true =DOMElement $node */
	final protected function isTRStructure( DOMNode $node ): bool {
		return $node->childNodes->length && AssertDOMElement::isValid( $node, type: 'tr' );
	}

	/** @phpstan-assert-if-true =DOMElement $node */
	final protected function isTHorTDStructure( mixed $node ): bool {
		return $node instanceof DOMElement && in_array( $node->tagName, array( 'th', 'td' ), strict: true );
	}

	/** @return ?Iterator<int,DOMNode> */
	protected function fromCurrentStructure( DOMNode $node ): ?Iterator {
		if ( ! AssertDOMElement::isValid( $node, type: 'table' ) ) {
			$this->findTableStructureIn( $node );

			return null;
		}

		/** @var ?Iterator<int,DOMNode> */
		return $this->isTargetedTable( $node ) && $node->childNodes->length
			? $node->childNodes->getIterator()
			: null;
	}

	/** @return ?array{0:list<string>,1:list<ThReturn>} */
	protected function tableHeadContentFrom( DOMNode $node, ?DOMElement $row = null ): ?array {
		if ( ! AssertDOMElement::isValid( $node, type: 'thead' ) ) {
			return null;
		}

		/** @var Iterator<int,DOMNode> */
		$headIterator = $node->childNodes->getIterator();

		while ( ! $row && $headIterator->valid() ) {
			$this->isTRStructure( $node = $headIterator->current() ) && ( $row = $node );

			$headIterator->next();
		}

		return $row ? $this->inferTableHeadFrom( $row->childNodes ) : null;
	}

	/** @return ?array{0:?array{0:list<string>,1:list<ThReturn>},1:DOMElement} */
	protected function discoveredStructureIn( DOMNode $node, ?DOMElement $body = null ): ?array {
		if ( ! $tableIterator = $this->fromCurrentStructure( $node ) ) {
			return null;
		}

		// Currently, <caption> element is skipped.
		if ( AssertDOMElement::isValid( $tableIterator->current(), type: 'caption' ) ) {
			$tableIterator->next();
		}

		if ( $head = $this->tableHeadContentFrom( $tableIterator->current() ) ) {
			$tableIterator->next();
		}

		while ( ! $body && $tableIterator->valid() ) {
			AssertDOMElement::isValid( $node = $tableIterator->current(), type: 'tbody' ) && ( $body = $node );

			$tableIterator->next();
		}

		return $body ? array( $head, $body ) : null;
	}

	/**
	 * @param ?array{0:list<string>,1:list<ThReturn>} $head
	 * @param DOMElement                              $body
	 * @return Iterator<array-key,ArrayObject<array-key,TdReturn>>
	 */
	protected function fromTableBodyRowStructure( ?array $head, DOMElement $body ): Iterator {
		/** @var Iterator<int,DOMElement> Expected. May contain comment nodes. */
		$rowIterator    = $body->childNodes->getIterator();
		$rowTransformer = $this->discoveredTable__transformers['tr'] ?? null;
		$headInspected  = false;
		$position       = 0;

		while ( $rowIterator->valid() ) {
			if ( ! $tableRow = AssertDOMElement::nextIn( $rowIterator, type: 'tr' ) ) {
				return;
			}

			! $headInspected && $this->inspectHeadInBody( $head, $rowIterator, $tableRow );

			$headInspected = true;

			if ( ! $tableRow = AssertDOMElement::nextIn( $rowIterator, type: 'tr' ) ) {
				return;
			}

			// TODO: add support whether to skip yielding empty <tr> or not.
			if ( ! trim( $tableRow->textContent ) ) {
				return;
			}

			$head && ! $this->getColumnNames() && $this->setColumnNames( $head[0], $this->getTableId( true ) );

			$content = $rowTransformer?->transform( $tableRow, $position, $this ) ?? $tableRow->childNodes;

			if ( $content instanceof CollectionSet ) {
				yield $content->key => $content->value;
			} else {
				yield new ArrayObject( $this->inferTableDataFrom( $content ) );
			}

			++$position;

			$rowIterator->next();
		}//end while
	}

	/** @param ?array{0:list<string>,1:list<ThReturn>} $head */
	private function inspectHeadInBody( ?array &$head, Iterator $iterator, DOMNode $row ): void {
		$head ??= $headDiscoveredInBody = $this->inferTableHeadFrom( $row->childNodes );

		// Contents of <tr> as head MUST NOT BE COLLECTED as a Table Data also.
		// Advance iterator to next <tr> when current row is collected as head.
		( $headDiscoveredInBody ?? false ) && $iterator->next();

		$head && $this->registerCurrentTableTH( ...$head );
	}

	private function discoveredTargetedTable( mixed $node ): bool {
		return ! $this->shouldPerform__allTableDiscovery
			&& AssertDOMElement::isValid( $node )
			&& $this->isTargetedTable( $node );
	}

	/**
	 * @param Transformer<TdReturn>     $transformer
	 * @param array<array-key,TdReturn> $data
	 * @return ?TdReturn
	 */
	private function collectedTDFrom( DOMElement $node, Transformer $transformer, array &$data ): mixed {
		$count    = $this->getCurrentIterationCountOf( Table::Column );
		$position = $count ? $count - 1 : 0;
		$value    = $transformer->transform( $node, $position, $this );

		return ( ! is_null( $value ) && '' !== $value )
			? ( $data[ $this->getCurrentColumnName() ?? $position ] = $value )
			: null;
	}

	/**
	 * @param list<string>   $names
	 * @param list<ThReturn> $contents
	 */
	private function registerCurrentTableTH( array $names, array $contents ): void {
		$tableId                                      = $this->getTableId( current: true );
		$this->discoveredTable__headNames[ $tableId ] = SplFixedArray::fromArray( $names );
		$this->discoveredTable__heads[ $tableId ]     = new ArrayObject( $contents );

		$this->registerCurrentIterationTH( false );
	}

	private function registerCurrentIterationTH( int|false $index = 0 ): void {
		if ( false === $index ) {
			unset( $this->currentIteration__headCount, $this->currentIteration__headIndex );

			return;
		}

		$this->currentIteration__headIndex = $index;
		$this->currentIteration__headCount = $index + 1;
	}

	private function registerCurrentIterationTD( ?string $index, int $count ): void {
		$this->currentIteration__columnCount[ $this->currentTable__bodyId ] = $count;
		$index && $this->currentIteration__columnIndex                      = $index;
	}
}
