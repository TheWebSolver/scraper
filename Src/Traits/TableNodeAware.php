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
use TheWebSolver\Codegarage\Scraper\Marshaller\Marshaller;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

/**
 * @template ThReturn
 * @template TdReturn
 */
trait TableNodeAware {
	/** @placeholder `1:` methodname, `2:` static classname, `3:` reason. */
	final public const USE_EVENT_DISPATCHER = 'Calling "%1$s::%2$s()" is not allowed before table found. Using method "%1$s::dispatch()", %3$s';

	/**
	 * @var array{
	 *   tr ?: Transformer<CollectionSet<TdReturn>|iterable<int,string|DOMNode>>,
	 *   th ?: Transformer<ThReturn>,
	 *   td ?: Transformer<TdReturn>
	 * }
	 */
	private array $transformer__instances;

	private bool $shouldPerform__allTableScan = false;

	/** @var Closure( static ): void */
	private Closure $foundTable__event;
	/** @var int[] */
	private array $foundTable__bodyIds = array();

	/** @var array<int,ArrayObject<int,ThReturn>> */
	private array $scannedTable__heads = array();
	/** @var array<int,SplFixedArray<string>> */
	private array $scannedTable__headNames = array();
	/** @var array<int,Iterator<array-key,ArrayObject<array-key,TdReturn>>> */
	private array $scannedTable__rows = array();

	private int $currentTable__splId;
	private int $currentTable__bodyId;
	/** @var array<int,list<string>> */
	private array $currentTable__columnNames;

	private int $currentIteration__headCount;
	private int $currentIteration__headIndex;
	/** @var array<int,int> */
	private array $currentIteration__columnCount   = array();
	private ?string $currentIteration__columnIndex = null;

	public function setColumnNames( array $keys ): void {
		$id = $this->getTableId( current: true ) ?: throw new ScraperError(
			sprintf( self::USE_EVENT_DISPATCHER, static::class, __FUNCTION__, 'set column names.' )
		);

		$this->currentTable__columnNames[ $id ] = $keys;
	}

	public function withTransformers( array $transformers ): static {
		isset( $transformers['tr'] ) && ( $this->transformer__instances['tr'] = $transformers['tr'] );
		isset( $transformers['th'] ) && ( $this->transformer__instances['th'] = $transformers['th'] );
		isset( $transformers['td'] ) && ( $this->transformer__instances['td'] = $transformers['td'] );

		return $this;
	}

	/** @return ($current is true ? int : int[]) */
	public function getTableId( bool $current = false ): int|array {
		return $current ? $this->currentTable__bodyId ?? 0 : $this->foundTable__bodyIds;
	}

	/** @return list<string> */
	public function getColumnNames(): array {
		return $this->currentTable__columnNames[ $this->currentTable__bodyId ] ?? array();
	}

	public function getCurrentColumnName(): ?string {
		return $this->currentIteration__columnIndex;
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
		return $namesOnly ? $this->scannedTable__headNames : $this->scannedTable__heads;
	}

	/** @return array<int,Iterator<array-key,ArrayObject<array-key,TdReturn>>> */
	public function getTableData(): array {
		return $this->scannedTable__rows;
	}

	public function withAllTables( bool $trace = true ): static {
		$this->shouldPerform__allTableScan = $trace;

		return $this;
	}

	public function traceTableIn( iterable $elementList ): void {
		$elementList instanceof DOMNodeList || throw new InvalidSource(
			sprintf( 'Table Node tracer only accepts "%1$s".', DOMNodeList::class )
		);

		foreach ( $elementList as $node ) {
			if ( ! $tableElements = $this->tableStructureIn( $node ) ) {
				continue;
			}

			[$head, $body] = $tableElements;
			$id            = $this->currentTable__splId + spl_object_id( $body );
			$iterator      = $this->iteratorFromTableContent( $id, $head, $body );

			$iterator->valid() && ( $this->scannedTable__rows[ $id ] = $iterator );

			if ( $this->foundTargetedTable( $node ) ) {
				break;
			}
		}
	}

	/** @return ?array{0:list<string>,1:list<ThReturn>} */
	public function inferTableHeadFrom( iterable $elementList ): ?array {
		$thTransformer = $this->transformer__instances['th'] ?? null;
		$names         = $collection = array();
		$skippedNodes  = 0;

		foreach ( $elementList as $currentIndex => $headNode ) {
			if ( ! AssertDOMElement::isValid( $headNode, type: 'th' ) ) {
				++$skippedNodes;

				continue;
			}

			$foundPosition = $currentIndex - $skippedNodes;

			$this->registerCurrentIterationTH( $foundPosition );

			$trimmed      = trim( $headNode->textContent );
			$content      = $thTransformer?->transform( $headNode, $foundPosition ) ?? $trimmed;
			$names[]      = is_string( $content ) ? $content : $trimmed;
			$collection[] = $content;
		}

		return $collection ? array( $names, $collection ) : null;
	}

	public function inferTableDataFrom( iterable $elementList ): array {
		$data          = array();
		$keys          = $this->getColumnNames();
		$foundPosition = $skippedNodes = $this->currentIteration__columnCount[ $this->currentTable__bodyId ] = 0;

		/** @var Transformer<TdReturn> Marshaller's TReturn is always string. */
		$transformer = $this->transformer__instances['td'] ?? new Marshaller();

		foreach ( $elementList as $currentIndex => $node ) {
			if ( ! $this->isNodeTHorTD( $node ) ) {
				++$skippedNodes;

				continue;
			}

			$foundPosition = $currentIndex - $skippedNodes;
			$indexKey      = isset( $keys[ $foundPosition ] ) ? $keys[ $foundPosition ] : null;

			$this->registerCurrentIterationTD( $indexKey, count: $foundPosition + 1 );
			$this->collectFromCurrentIterationTD( array( $node, $indexKey, $foundPosition, $transformer ), $data )
				&& $this->shouldPerform__allTableScan
				&& ( $nodes = $node->childNodes )->length > 1
				&& $this->traceTableIn( $nodes );
		}

		$this->currentIteration__columnIndex = null;

		return $data;
	}

	protected function flushTableNodeTrace(): void {
		unset(
			$this->foundTable__bodyIds,
			$this->scannedTable__headNames,
			$this->scannedTable__heads,
			$this->scannedTable__rows,
			$this->transformer__instances,
		);
	}

	/** @param callable( static ): void $event */
	protected function dispatch( callable $event ): void {
		$this->foundTable__event = $event( ... );
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- To be used by exhibiting class.
	protected function isTargetedTable( DOMElement $node ): bool {
		return true;
	}

	final protected function setTableId( int $id ): void {
		if ( ! in_array( $id, $this->foundTable__bodyIds, true ) ) {
			$this->foundTable__bodyIds[] = $id;
			$this->currentTable__bodyId  = $id;
		}
	}

	/** @param DOMNodeList<DOMNode> $nodes */
	final protected function findTableNodeIn( DOMNodeList $nodes ): void {
		( ! $this->foundTable__bodyIds || $this->shouldPerform__allTableScan )
			&& $nodes->count() && $this->traceTableIn( $nodes );
	}

	/** @phpstan-assert-if-true =DOMElement $node */
	final protected function isNodeTRWithTDContent( DOMNode $node ): bool {
		return $node->childNodes->count() && AssertDOMElement::isValid( $node, type: 'tr' );
	}

	/** @phpstan-assert-if-true =DOMElement $node */
	final protected function isNodeTHorTD( mixed $node ): bool {
		return $node instanceof DOMElement && in_array( $node->tagName, array( 'th', 'td' ), strict: true );
	}

	/** @return ?Iterator<int,DOMNode> */
	protected function fromTargetedHtmlTable( DOMNode $node ): ?Iterator {
		if ( ! AssertDOMElement::isValid( $node, type: 'table' ) ) {
			$this->findTableNodeIn( $node->childNodes );

			return null;
		}

		/** @var ?Iterator<int,DOMNode> */
		return $this->isTargetedTable( $node ) && $node->hasChildNodes()
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
			$this->isNodeTRWithTDContent( $node = $headIterator->current() ) && ( $row = $node );

			$headIterator->next();
		}

		return $row ? $this->inferTableHeadFrom( $row->childNodes ) : null;
	}

	/** @return ?array{0:?array{0:list<string>,1:list<ThReturn>},1:DOMElement} */
	protected function tableStructureIn( DOMNode $node, ?DOMElement $body = null ): ?array {
		if ( ! $tableIterator = $this->fromTargetedHtmlTable( $node ) ) {
			return null;
		}

		$this->currentTable__splId = spl_object_id( $node );

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
	protected function iteratorFromTableContent( int $tableId, ?array $head, DOMElement $body ): Iterator {
		$this->setTableId( $tableId );

		isset( $this->foundTable__event ) && ( $this->foundTable__event )( $this );

		/** @var Iterator<int,DOMElement> Expected. May contain comment nodes. */
		$rowIterator    = $body->childNodes->getIterator();
		$rowTransformer = $this->transformer__instances['tr'] ?? null;
		$scannedTh      = $registeredTh = false;
		$position       = 0;

		while ( $rowIterator->valid() ) {
			if ( ! AssertDOMElement::nextIn( $rowIterator, type: 'tr' ) ) {
				return;
			}

			if ( ! $scannedTh && ! $head ) {
				$scannedTh = true;
				$head      = $this->inferTableHeadFrom( $rowIterator->current()->childNodes );

				// Contents of <tr> as head MUST NOT BE COLLECTED as a Table Data also.
				$head && $rowIterator->next();
			}

			if ( ! $registeredTh && $head ) {
				$this->registerCurrentTableTH( $tableId, ...$head );

				$registeredTh = true;
			}

			if ( ! AssertDOMElement::nextIn( $rowIterator, type: 'tr' ) ) {
				return;
			}

			$current = $rowIterator->current();

			$rowIterator->next();

			// TODO: add support whether to skip yielding empty <tr> or not.
			if ( trim( $current->textContent ) ) {
				$row = $rowTransformer?->transform( $current, $position ) ?? $current->childNodes;

				$head && ! $this->getColumnNames() && $this->setColumnNames( $head[0] );

				if ( $row instanceof CollectionSet ) {
					yield $row->key => $row->value;
				} else {
					yield new ArrayObject( $this->inferTableDataFrom( $row ) );
				}

				++$position;
			}
		}//end while
	}

	private function foundTargetedTable( mixed $node ): bool {
		return ! $this->shouldPerform__allTableScan
			&& AssertDOMElement::isValid( $node )
			&& $this->isTargetedTable( $node );
	}

	/**
	 * @param array{0:DOMElement,1:?array-key,2:int,3:Transformer<TdReturn>} $args
	 * @param array<array-key,TdReturn>                                      $data
	 * @return ?TdReturn
	 */
	private function collectFromCurrentIterationTD( array $args, array &$data ): mixed {
		[$node, $key, $position, $transformer] = $args;
		$key                                 ??= $position;
		$val                                   = $transformer->transform( $node, $position );

		return ( ! is_null( $val ) && '' !== $val ) ? ( $data[ $key ] = $val ) : null;
	}

	/**
	 * @param list<string>   $names
	 * @param list<ThReturn> $contents
	 */
	private function registerCurrentTableTH( int $tableId, array $names, array $contents ): void {
		$this->scannedTable__headNames[ $tableId ] = SplFixedArray::fromArray( $names );
		$this->scannedTable__heads[ $tableId ]     = new ArrayObject( $contents );

		$this->registerCurrentIterationTH( false );
	}

	private function registerCurrentIterationTH( int|false $index = 0 ): void {
		if ( is_bool( $index ) ) {
			unset( $this->currentIteration__headCount, $this->currentIteration__headIndex );

			return;
		}

		$this->currentIteration__headIndex = $index;
		$this->currentIteration__headCount = $index + 1;
	}

	private function registerCurrentIterationTD( ?string $index, int $count ): void {
		$this->currentIteration__columnCount[ $this->currentTable__bodyId ] = $count;
		$this->currentIteration__columnIndex                                = $index;
	}
}
