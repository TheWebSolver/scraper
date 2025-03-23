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
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Marshaller\Marshaller;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

/**
 * @template ThReturn
 * @template TdReturn
 */
trait TableNodeAware {
	/** @var Closure( static ): void */
	private Closure $foundTable__event;
	private bool $shouldPerform__allTableScan = false;
	/** @var int[] */
	private array $foundTable__ids = array();
	/** @var array<int,ArrayObject<int,ThReturn>> */
	private array $scannedTable__heads;
	/** @var array<int,array<int,ArrayObject<array-key,TdReturn>>> */
	private array $scannedTable__rows = array();
	/** @var array<int,SplFixedArray<string>> */
	private array $scannedTable__headNames = array();
	/** @var array{tr?:Transformer<ArrayObject<array-key,TdReturn>|DOMElement>,th?:Transformer<ThReturn>,td?:Transformer<TdReturn>} */
	private array $transformer__instances;
	private int $currentTable__id;
	/** @var array<int,list<string>> */
	private array $currentTable__columnNames;

	/** @var array<int,int> */
	private array $currentIteration__columnCount   = array();
	private ?string $currentIteration__columnIndex = null;

	/** @param list<string> $keys */
	public function setColumnNames( array $keys ): void {
		$this->currentTable__columnNames[ $this->currentTable__id ] = $keys;
	}

	public function withTransformers( array $transformers ): static {
		isset( $transformers['tr'] ) && ( $this->transformer__instances['tr'] = $transformers['tr'] );
		isset( $transformers['th'] ) && ( $this->transformer__instances['th'] = $transformers['th'] );
		isset( $transformers['td'] ) && ( $this->transformer__instances['td'] = $transformers['td'] );

		return $this;
	}

	/** @return ($current is true ? int : int[]) */
	public function getTableId( bool $current = false ): int|array {
		return $current ? $this->currentTable__id : $this->foundTable__ids;
	}

	/** @return list<string> */
	public function getColumnNames(): array {
		return $this->currentTable__columnNames[ $this->currentTable__id ] ?? array();
	}

	public function getCurrentColumnName(): ?string {
		return $this->currentIteration__columnIndex;
	}

	public function getCurrentIterationCountOf( Table $element ): int {
		return ( match ( $element ) {
			default       => null,
			Table::Column => $this->currentIteration__columnCount,
		} )[ $this->currentTable__id ] ?? 0;
	}

	/** @return ($namesOnly is true ? array<int,SplFixedArray<string>> : array<int,ArrayObject<int,ThReturn>>) */
	public function getTableHead( bool $namesOnly = false ): array {
		return $namesOnly ? $this->scannedTable__headNames : $this->scannedTable__heads;
	}

	/** @return array<int,array<int,ArrayObject<array-key,TdReturn>>> */
	public function getTableData(): array {
		return $this->scannedTable__rows;
	}

	public function inferTableDataFrom( iterable $elementList ): array {
		$data          = array();
		$keys          = $this->getColumnNames();
		$foundPosition = $skippedNodes = $this->currentIteration__columnCount[ $this->currentTable__id ] = 0;

		/** @var Transformer<TdReturn> Marshaller's TReturn is always string. */
		$transformer = $this->transformer__instances['td'] ?? new Marshaller();

		foreach ( $elementList as $currentIndex => $node ) {
			if ( ! $this->isNodeTHorTD( $node ) ) {
				++$skippedNodes;

				continue;
			}

			$foundPosition = $currentIndex - $skippedNodes;
			$indexKey      = isset( $keys[ $foundPosition ] ) ? $keys[ $foundPosition ] : null;

			$this->tickCurrentIterationTD( $indexKey, count: $foundPosition + 1 );

			$this->collectFromCurrentIterationTD( array( $node, $indexKey, $foundPosition, $transformer ), $data )
				&& $this->shouldPerform__allTableScan
				&& ( $nodes = $node->childNodes )->length > 1
				&& $this->traceTableIn( $nodes );
		}

		$this->currentIteration__columnIndex = null;

		return $data;
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
			if ( ! $contents = $this->validateContentsOf( $node, $id = spl_object_id( $node ) ) ) {
				continue;
			}

			$iterator = $this->fromTableContents( $id, ...$contents );

			$iterator->valid() && ( $this->scannedTable__rows[ $id ] = iterator_to_array( $iterator ) );

			if ( $this->foundTargetedTable( $node ) ) {
				break;
			}
		}
	}

	protected function flushTableNodeTrace(): void {
		unset(
			$this->foundTable__ids,
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
		if ( ! in_array( $id, $this->foundTable__ids, true ) ) {
			$this->foundTable__ids[] = $id;
			$this->currentTable__id  = $id;
		}
	}

	/** @param DOMNodeList<DOMNode> $nodes */
	final protected function findTableNodeIn( DOMNodeList $nodes ): void {
		( ! $this->foundTable__ids || $this->shouldPerform__allTableScan )
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

	/** @return ?list<string> */
	protected function scanTableHead( DOMNode $node, int $tableId ): ?array {
		$thTransformer = $this->transformer__instances['th'] ?? null;
		$collection    = new ArrayObject();
		$names         = array();
		$position      = 0;

		foreach ( $node->childNodes as $node ) {
			if ( ! AssertDOMElement::isValid( $node, type: 'th' ) ) {
				continue;
			}

			$trimmed = trim( $node->textContent );
			$content = $thTransformer?->transform( $node, $position++ ) ?? $trimmed;
			$names[] = is_string( $content ) ? $content : $trimmed;

			$collection->append( $content );
		}

		if ( ! $collection->count() ) {
			return null;
		}

		$this->setTableId( $tableId );

		$this->scannedTable__headNames[ $tableId ] = SplFixedArray::fromArray( $names );
		$this->scannedTable__heads[ $tableId ]     = $collection;

		return $names;
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

	/** @return ?list<string> */
	protected function tableHeadContentFrom( DOMNode $node, int $tableId, ?DOMElement $row = null ): ?array {
		if ( 'thead' !== $node->nodeName ) {
			return null;
		}

		/** @var Iterator<int,DOMNode> */
		$headIterator = $node->childNodes->getIterator();

		while ( ! $row && $headIterator->valid() ) {
			$this->isNodeTRWithTDContent( $node = $headIterator->current() ) && ( $row = $node );

			$headIterator->next();
		}

		return $row ? $this->scanTableHead( $row, $tableId ) : null;
	}

	/** @return ?array{0:?list<string>,1:?DOMElement} */
	protected function tableContentFrom( DOMNode $node, int $tableId, ?DOMElement $body = null ): ?array {
		if ( ! $tableIterator = $this->fromTargetedHtmlTable( $node ) ) {
			return null;
		}

		// Currently, <caption> element is skipped.
		if ( AssertDOMElement::isValid( $tableIterator->current(), type: 'caption' ) ) {
			$tableIterator->next();
		}

		if ( $head = $this->tableHeadContentFrom( $tableIterator->current(), $tableId ) ) {
			$tableIterator->next();
		}

		while ( ! $body && $tableIterator->valid() ) {
			AssertDOMElement::isValid( $node = $tableIterator->current(), type: 'tbody' ) && ( $body = $node );

			$tableIterator->next();
		}

		return array( $head, $body );
	}

	/** @return ?array{0:?list<string>,1:DOMElement} */
	protected function validateContentsOf( DOMNode $node, int $tableId ): ?array {
		$content = $this->tableContentFrom( $node, $tableId );

		return ! $content || ! $content[1] ? null : $content;
	}

	/**
	 * @param ?list<string> $head
	 * @param DOMElement    $body
	 * @return Iterator<int,ArrayObject<array-key,TdReturn>>
	 */
	protected function fromTableContents( int $tableId, ?array $head, DOMElement $body ): Iterator {
		$this->setTableId( $tableId );

		isset( $this->foundTable__event ) && ( $this->foundTable__event )( $this );

		/** @var Iterator<int,DOMElement> Expected. May contain comment nodes. */
		$rowIterator    = $body->childNodes->getIterator();
		$rowTransformer = $this->transformer__instances['tr'] ?? null;
		$scannedTh      = false;
		$position       = 0;

		while ( $rowIterator->valid() ) {
			if ( ! AssertDOMElement::nextIn( $rowIterator, type: 'tr' ) ) {
				return;
			}

			if ( ! $scannedTh && ! $head ) {
				$scannedTh = true;
				$head    ??= $this->scanTableHead( $rowIterator->current(), $tableId );

				// Contents of <tr> as head MUST NOT BE COLLECTED as a Table Data also.
				$head && $rowIterator->next();
			}

			if ( ! AssertDOMElement::nextIn( $rowIterator, type: 'tr' ) ) {
				return;
			}

			$current = $rowIterator->current();

			$rowIterator->next();

			// TODO: add support whether to skip yielding empty <tr> or not.
			if ( trim( $current->textContent ) ) {
				$row = $rowTransformer?->transform( $current, $position++ ) ?? $current;

				$head && ! $this->getColumnNames() && $this->setColumnNames( $head );

				yield $row instanceof ArrayObject ? $row : new ArrayObject( $this->inferTableDataFrom( $row->childNodes ) );
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

	private function tickCurrentIterationTD( ?string $index, int $count ): void {
		$this->currentIteration__columnCount[ $this->currentTable__id ] = $count;
		$this->currentIteration__columnIndex                            = $index;
	}
}
