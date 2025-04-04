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
use TheWebSolver\Codegarage\Scraper\Helper\Normalize;
use TheWebSolver\Codegarage\Scraper\Data\CollectionSet;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Marshaller\TableColumnMarshaller;

/**
 * @template ThReturn
 * @template TdReturn
 */
trait HtmlTableFromNode {
	/** @placeholder `1:` static classname, `2:` throwing methodname, `3:` reason. */
	final public const USE_EVENT_DISPATCHER = 'Calling "%1$s::%2$s()" is not allowed before table is discovered. Use listener passed to "%1$s::subscribeWith()" to %3$s';

	private bool $shouldPerform__allTableDiscovery = false;

	/** @var list<Table> */
	private array $discoveredTable__excludedStructures = array();
	/**
	 * @var array{
	 *   tr      ?: Transformer<CollectionSet<TdReturn>|iterable<int,string|DOMNode>>,
	 *   th      ?: Transformer<ThReturn>,
	 *   td      ?: Transformer<TdReturn>,
	 *   caption ?: Transformer<string>
	 * }
	 */
	private array $discoveredTable__transformers;
	/** @var array<string,Closure( static, DOMElement ): mixed> */
	private array $discoveredTable__eventListeners;
	/** @var int[] */
	private array $discoveredTable__bodyIds = array();
	/** @var array<int,?string> */
	private array $discoveredTable__captions = array();
	/** @var array<int,ArrayObject<int,ThReturn>> */
	private array $discoveredTable__heads = array();
	/** @var array<int,SplFixedArray<string>> */
	private array $discoveredTable__headNames = array();
	/** @var array<int,Iterator<array-key,ArrayObject<array-key,TdReturn>>> */
	private array $discoveredTable__rows = array();

	private int $currentTable__splId;
	private int $currentTable__bodyId;
	/** @var array<int,array{0:array<int,string>,1:array<int,int>}> */
	private array $currentTable__columnNames;
	/** @var array<int,int> */
	private array $currentTable__lastColumn = array();

	private bool $currentIteration__allTableHeads = true;
	private int $currentIteration__headCount;
	private int $currentIteration__headIndex;
	private string $currentIteration__columnName;
	/** @var array<int,int> */
	private array $currentIteration__columnCount = array();

	public function setColumnNames( array $keys, int $id, int ...$offset ): void {
		( $id && $this->getTableId( current: true ) === $id ) || throw new ScraperError(
			sprintf( self::USE_EVENT_DISPATCHER, static::class, __FUNCTION__, 'set column names.' )
		);

		if ( ! $keys ) {
			return;
		}

		[$columns, $flippedOffset, $lastIndex]  = Normalize::listWithOffset( $keys, $offset );
		$this->currentTable__columnNames[ $id ] = array( $columns, $flippedOffset );
		$this->currentTable__lastColumn[ $id ]  = $lastIndex;
	}

	public function traceWithout( Table ...$targets ): static {
		$this->discoveredTable__excludedStructures = $targets;

		return $this;
	}

	/** @param callable( static, DOMElement ): mixed $eventListener */
	public function subscribeWith( callable $eventListener, Table $target ): static {
		$this->discoveredTable__eventListeners[ $target->value ] = $eventListener( ... );

		return $this;
	}

	public function transformWith( Transformer $transformer, Table $target ): static {
		$this->discoveredTable__transformers[ $target->value ] = $transformer;

		return $this;
	}

	/** @return ($current is true ? int : int[]) */
	public function getTableId( bool $current = false ): int|array {
		return $current ? $this->currentTable__bodyId ?? 0 : $this->discoveredTable__bodyIds;
	}

	/** @return array<int,string> */
	public function getColumnNames(): array {
		return $this->currentTable__columnNames[ $this->currentTable__bodyId ][0] ?? array();
	}

	public function getTableCaption(): array {
		return $this->discoveredTable__captions;
	}

	public function getCurrentColumnName(): ?string {
		return $this->currentIteration__columnName ?? null;
	}

	public function getCurrentIterationCountOf( Table $element, bool $offsetInclusive = false ): ?int {
		if ( Table::Head === $element ) {
			return $this->currentIteration__headCount ?? null;
		} elseif ( Table::Column === $element ) {
			$count = $this->currentIteration__columnCount[ $this->currentTable__bodyId ] ?? null;

			if ( null === $count ) {
				return null;
			}

			$column = $this->currentTable__columnNames[ $this->currentTable__bodyId ] ?? null;

			return ! $offsetInclusive && $column ? $count - count( $column[1] ) : $count;
		}

		return null;
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

	public function inferTableDataFrom( iterable $elementList ): array {
		$data         = array();
		$columns      = $this->currentTable__columnNames[ $this->currentTable__bodyId ] ?? array();
		$keys         = $columns[0] ?? array();
		$offset       = $columns[1] ?? array();
		$lastPosition = $this->currentTable__lastColumn[ $this->currentTable__bodyId ] ?? null;
		$skippedNodes = $this->currentIteration__columnCount[ $this->currentTable__bodyId ] = 0;

		/** @var Transformer<TdReturn> Marshaller's TReturn is always string. */
		$transformer = $this->discoveredTable__transformers[ Table::Column->value ] ?? new TableColumnMarshaller();

		foreach ( $elementList as $currentIndex => $node ) {
			if ( ! $this->isTableColumnStructure( $node ) ) {
				++$skippedNodes;

				continue;
			}

			$currentPosition = $currentIndex - $skippedNodes;

			if ( false !== ( $offset[ $currentPosition ] ?? false ) ) {
				continue;
			}

			if ( $this->hasColumnReachedAtLastPosition( $currentPosition, $lastPosition ) ) {
				break;
			}

			$this->registerCurrentIterationTableColumn( $keys[ $currentPosition ] ?? null, $currentPosition + 1 );

			$this->collectedTableColumnFrom( $node, $transformer, $data )
				&& $this->findTableStructureIn( $node, minChildNodesCount: 1 );
		}//end foreach

		unset( $this->currentIteration__columnName );

		return $data;
	}

	/** @param DOMNodeList<DOMNode> $elementList */
	protected function inferTableFromDOMNodeList( DOMNodeList $elementList ): void {
		foreach ( $elementList as $node ) {
			if ( ! $tableStructure = $this->traceStructureFrom( $node ) ) {
				continue;
			}

			[$bodyNode, $captionNode, $headNode] = $tableStructure;

			$splId = $this->currentTable__splId = spl_object_id( $node );
			$id    = $splId * spl_object_id( $bodyNode );

			$this->dispatchEventListenerForDiscoveredTable( $id, $bodyNode );

			$this->discoveredTable__captions[ $id ] = $captionNode
				? $this->captionStructureContentFrom( $captionNode )
				: null;

			$head     = $headNode ? $this->headStructureContentFrom( $headNode ) : null;
			$iterator = $this->bodyStructureIteratorFrom( $head, $bodyNode );

			$iterator->valid() && ( $this->discoveredTable__rows[ $id ] = $iterator );

			if ( $this->discoveredTargetedTable( $node ) ) {
				break;
			}
		}//end foreach
	}

	/**
	 * Infers table head from given element list.
	 *
	 * @param DOMNodeList<DOMNode> $elementList
	 * @return ?array{0:list<string>,1:list<ThReturn>}
	 * @throws InvalidSource When element list is not DOMNodeList.
	 */
	protected function inferTableHeadFrom( DOMNodeList $elementList ): ?array {
		$thTransformer = $this->discoveredTable__transformers[ Table::Head->value ] ?? null;
		$names         = $collection = array();
		$skippedNodes  = 0;

		foreach ( $elementList as $currentIndex => $headNode ) {
			if ( ! AssertDOMElement::isValid( $headNode, Table::Head ) ) {
				$this->tickCurrentHeadIterationSkippableNode( $headNode );

				++$skippedNodes;

				continue;
			}

			$position = $currentIndex - $skippedNodes;

			$this->registerCurrentIterationTableHead( $position );

			$trimmed      = trim( $headNode->textContent );
			$content      = $thTransformer?->transform( $headNode, $position, $this ) ?? $trimmed;
			$names[]      = is_string( $content ) ? $content : $trimmed;
			$collection[] = $content;
		}

		return $collection ? array( $names, $collection ) : null;
	}

	final protected function flushDiscoveredTableHooks(): void {
		unset(
			$this->discoveredTable__transformers,
			$this->discoveredTable__eventListeners
		);
	}

	final protected function flushDiscoveredTableStructure(): void {
		unset(
			$this->discoveredTable__heads,
			$this->discoveredTable__headNames,
			$this->discoveredTable__rows,
		);
	}

	final protected function dispatchEventListenerForDiscoveredTable( int $id, DOMElement $body ): void {
		if ( ! in_array( $id, $this->discoveredTable__bodyIds, true ) ) {
			$this->discoveredTable__bodyIds[] = $this->currentTable__bodyId = $id;
		}

		isset( $this->discoveredTable__eventListeners[ Table::TBody->value ] )
			&& ( $this->discoveredTable__eventListeners[ Table::TBody->value ] )( $this, $body );

		unset( $this->discoveredTable__eventListeners[ Table::TBody->value ] );
	}

	final protected function findTableStructureIn( DOMNode $node, int $minChildNodesCount = 0 ): void {
		( ! $this->getTableId() || $this->shouldPerform__allTableDiscovery )
			&& ( ( $nodes = $node->childNodes )->length > $minChildNodesCount )
			&& $this->inferTableFromDOMNodeList( $nodes );
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- To be used by exhibiting class.
	protected function isTargetedTable( DOMElement $node ): bool {
		return true;
	}

	/** @phpstan-assert-if-true =DOMElement $node */
	protected function isTableRowStructure( DOMNode $node ): bool {
		return $node->childNodes->length && AssertDOMElement::isValid( $node, Table::Row );
	}

	/** @phpstan-assert-if-true =DOMElement $node */
	protected function isTableColumnStructure( mixed $node ): bool {
		return AssertDOMElement::isValid( $node, Table::Head ) || AssertDOMElement::isValid( $node, Table::Column );
	}

	/** @return ?Iterator<int,DOMNode> */
	private function fromCurrentStructure( DOMNode $node ): ?Iterator {
		if ( ! AssertDOMElement::isValid( $node, 'table' ) ) {
			$this->findTableStructureIn( $node );

			return null;
		}

		/** @var ?Iterator<int,DOMNode> */
		return $this->isTargetedTable( $node ) && $node->childNodes->length
			? $node->childNodes->getIterator()
			: null;
	}

	private function toNextStructureIfInCurrentPosition( Table $target, Iterator $tableIterator ): ?DOMElement {
		if ( AssertDOMElement::isValid( $node = $tableIterator->current(), $target ) ) {
			$tableIterator->next();

			return $this->shouldTraceTableStructure( $target ) ? $node : null;
		}

		return null;
	}

	private function captionStructureContentFrom( DOMElement $node ): ?string {
		$transformer = $this->discoveredTable__transformers[ Table::Caption->value ] ?? null;

		return $transformer?->transform( $node, 0, $this ) ?? trim( $node->textContent );
	}

	/** @return ?array{0:list<string>,1:list<ThReturn>} */
	private function headStructureContentFrom( DOMElement $node, ?DOMElement $row = null ): ?array {
		/** @var Iterator<int,DOMNode> */
		$headIterator = $node->childNodes->getIterator();

		while ( ! $row && $headIterator->valid() ) {
			$this->isTableRowStructure( $node = $headIterator->current() ) && ( $row = $node );

			$headIterator->next();
		}

		return $row ? $this->inferTableHeadFrom( $row->childNodes ) : null;
	}

	/** @return ?array{0:DOMElement,1:?DOMElement,2:?DOMElement} */
	private function traceStructureFrom( DOMNode $node ): ?array {
		if ( ! $tableIterator = $this->fromCurrentStructure( $node ) ) {
			return null;
		}

		$bodyNode = $captionNode = $headNode = null;

		while ( ! $bodyNode && $tableIterator->valid() ) {
			$captionNode = $this->toNextStructureIfInCurrentPosition( Table::Caption, $tableIterator );
			$headNode    = $this->toNextStructureIfInCurrentPosition( Table::THead, $tableIterator );
			$bodyNode    = $this->toNextStructureIfInCurrentPosition( Table::TBody, $tableIterator );

			$tableIterator->next();
		}

		return $bodyNode ? array( $bodyNode, $captionNode, $headNode ) : null;
	}

	/**
	 * @param ?array{0:list<string>,1:list<ThReturn>} $head
	 * @param DOMElement                              $body
	 * @return Iterator<array-key,ArrayObject<array-key,TdReturn>>
	 */
	private function bodyStructureIteratorFrom( ?array $head, DOMElement $body ): Iterator {
		/** @var Iterator<int,DOMElement> Expected. May contain comment nodes. */
		$rowIterator    = $body->childNodes->getIterator();
		$rowTransformer = $this->discoveredTable__transformers[ Table::Row->value ] ?? null;
		$headInspected  = false;
		$position       = 0;

		while ( $rowIterator->valid() ) {
			if ( ! $tableRow = AssertDOMElement::nextIn( $rowIterator, Table::Row ) ) {
				return;
			}

			! $headInspected && $this->inspectFirstRowForHeadStructure( $head, $rowIterator, $tableRow );

			$headInspected = true;

			if ( ! $tableRow = AssertDOMElement::nextIn( $rowIterator, Table::Row ) ) {
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
	private function inspectFirstRowForHeadStructure( ?array &$head, Iterator $iterator, DOMNode $row ): void {
		$firstRowContent = $this->inferTableHeadFrom( $row->childNodes );
		$head          ??= $this->currentIteration__allTableHeads ? $firstRowContent : null;

		// Contents of <tr> as head MUST NOT BE COLLECTED as a Table Data also.
		// Advance iterator to next <tr> if first row is collected as head.
		$this->currentIteration__allTableHeads && $iterator->next();

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
	private function collectedTableColumnFrom( DOMElement $node, Transformer $transformer, array &$data ): mixed {
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

		$this->registerCurrentIterationTableHead( false );
	}

	private function registerCurrentIterationTableHead( int|false $index = 0 ): void {
		if ( false === $index ) {
			unset( $this->currentIteration__headCount, $this->currentIteration__headIndex );

			return;
		}

		$this->currentIteration__headIndex = $index;
		$this->currentIteration__headCount = $index + 1;
	}

	private function registerCurrentIterationTableColumn( ?string $name, int $count ): void {
		$this->currentIteration__columnCount[ $this->currentTable__bodyId ] = $count;
		$name && $this->currentIteration__columnName                        = $name;
	}

	private function hasColumnReachedAtLastPosition( int $currentPosition, ?int $lastPosition ): bool {
		return null !== $lastPosition && $currentPosition > $lastPosition;
	}

	private function shouldTraceTableStructure( Table $target ): bool {
		return ! in_array( $target, $this->discoveredTable__excludedStructures, strict: true );
	}

	private function tickCurrentHeadIterationSkippableNode( DOMNode $node ): void {
		$this->currentIteration__allTableHeads
			&& $this->currentIteration__allTableHeads = XML_COMMENT_NODE === $node->nodeType;
	}
}
