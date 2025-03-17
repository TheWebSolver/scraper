<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits;

use DOMNode;
use Iterator;
use DOMElement;
use ArrayObject;
use DOMNodeList;
use SplFixedArray;
use TheWebSolver\Codegarage\Scraper\Helper\Normalize;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

trait TableNodeAware {
	private bool $scanAllTables = false;
	/** @var int[] */
	private array $tableIds = array();
	/** @var array<int,ArrayObject<int,string|array{0:string,1?:string,2?:DOMElement}>> */
	private array $tableHeads;
	/** @var array<int,array<int,ArrayObject<array-key,string|array{0:string,1?:string,2?:DOMElement}>>> */
	private array $tableRows = array();
	/** @var array<int,SplFixedArray<string>> */
	private array $tableHeadNames = array();
	/** @var array{tr?:Transformer<DOMNode[]>,th?:Transformer<string>,td?:Transformer<string>} */
	private array $transformers;
	private bool $onlyContents = false;

	/** @return int[] List of scanned tables' `spl_object_id()`. */
	public function getTableIds(): array {
		return $this->tableIds;
	}

	public function flush(): void {
		unset(
			$this->tableIds,
			$this->tableHeadNames,
			$this->tableHeads,
			$this->tableRows,
			$this->transformers,
		);
	}

	/**
	 * @return ($namesOnly is true
	 *   ? array<int,SplFixedArray<string>>
	 *   : array<int,ArrayObject<int,string|array{0:string,1?:string,2?:DOMElement}>>)
	 */
	public function getTableHead( bool $namesOnly = false ): array {
		return $namesOnly ? $this->tableHeadNames : $this->tableHeads;
	}

	/** @return array<int,array<int,ArrayObject<array-key,string|array{0:string,1?:string,2?:DOMElement}>>> */
	public function getTableData(): array {
		return $this->tableRows;
	}

	/** @param array{tr?:Transformer<DOMNode[]>,th?:Transformer<string>,td?:Transformer<string>} $transformers */
	public function useTransformers( array $transformers ): static {
		$this->transformers = $transformers;

		return $this;
	}

	public function withAllTableNodes( bool $scan = true ): static {
		$this->scanAllTables = $scan;

		return $this;
	}

	public function withOnlyContents(): static {
		$this->onlyContents = true;

		return $this;
	}

	/** @param DOMNodeList<DOMNode> $nodes */
	public function scanTableBodyNodeIn( DOMNodeList $nodes ): void {
		foreach ( $nodes as $node ) {
			$this->scanTableContents( $node );
		}
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- To be used by exhibiting class.
	protected function isTargetedTable( DOMElement $node ): bool {
		return true;
	}

	protected function scanTableContents( DOMNode $node ): void {
		if ( ! $body = $this->DOMTableBody( $node ) ) {
			return;
		}

		$this->tableIds[] = $tableId = spl_object_id( $node );
		$rowMarshaller    = $this->transformers['tr'] ?? null;

		foreach ( $body->childNodes as $tableRow ) {
			if ( ! $this->isTableRowWithTableData( $tableRow ) ) {
				continue;
			}

			if ( $this->scanTableHead( $tableRow->childNodes, $tableId ) ) {
				continue;
			}

			$nodes = $rowMarshaller?->collect( $tableRow, onlyContent: true )
			?? Normalize::nodesToArray( $tableRow->childNodes );

			$nodes && $this->scanTableData( $nodes, $tableId );
		}
	}

	/** @param DOMNodeList<DOMNode> $nodes */
	protected function scanTableHead( DOMNodeList $nodes, int $tableId ): bool {
		if ( ! $marshaller = ( $this->transformers['th'] ?? null ) ) {
			return false;
		}

		$heads = array_filter( iterator_to_array( $nodes ), $this->isTableHeadElement( ... ) );

		if ( count( $heads ) !== $nodes->count() ) {
			return false;
		}

		/** @var bool[] */
		$contentsOnly                     = array_pad( array(), count( $heads ), $this->onlyContents );
		$heads                            = array_map( $marshaller->collect( ... ), $heads, $contentsOnly );
		$this->tableHeadNames[ $tableId ] = SplFixedArray::fromArray( $marshaller->getContent() );
		$this->tableHeads[ $tableId ]     = new ArrayObject( $heads );

		$marshaller->flushContent();

		return true;
	}

	/** @param DOMNode[] $nodes */
	protected function scanTableData( array $nodes, int $tableId ): bool {
		if ( ! $rows = array_filter( $nodes, $this->isTableDataElement( ... ) ) ) {
			return false;
		}

		$this->tableRows[ $tableId ][] = new ArrayObject( $this->tableDataSet( $rows, $tableId ) );

		return true;
	}

	private function DOMTableBody( DOMNode $node ): ?DOMElement {
		if ( ! $this->isDomElement( $node, tagName: 'table' ) ) {
			$this->scanTableNodeIn( $node->childNodes );

			return null;
		}

		if ( ! $this->isTargetedTable( $node ) ) {
			return null;
		}

		/** @var Iterator<int,DOMNode> */
		$iterator = $node->childNodes->getIterator();
		$body     = null;

		while ( ! $body && $iterator->valid() ) {
			$current = $iterator->current();
			$body    = $this->isTableBodyElement( $current ) ? $current : null;

			$iterator->next();
		}

		return $body;
	}

	/**
	 * @param DOMElement[] $tableRows
	 * @return array<string|int,string|array{0:string,1?:string,2?:DOMElement}>
	 */
	private function tableDataSet( array $tableRows, int $tableId ): array {
		$data       = array();
		$marshaller = $this->transformers['td'] ?? null;

		foreach ( $tableRows as $tableData ) {
			$data[] = $marshaller?->collect( $tableData, $this->onlyContents ) ?? trim( $tableData->textContent );

			$tableData->childElementCount && ! $this->onlyContents
				&& $this->scanTableBodyNodeIn( $tableData->childNodes );
		}

		/** @var string[] $heads */
		$heads = ( $names = ( $this->tableHeadNames[ $tableId ] ?? null ) ) ? $names->toArray() : array();

		$marshaller?->flushContent();

		return $heads && count( $heads ) === count( $data ) ? array_combine( $heads, $data ) : $data;
	}

	/** @param DOMNodeList<DOMNode> $nodes */
	private function scanTableNodeIn( DOMNodeList $nodes ): void {
		( ! $this->tableIds || $this->scanAllTables )
			&& $nodes->count() && $this->scanTableBodyNodeIn( $nodes );
	}

	/** @phpstan-assert-if-true =DOMElement $node */
	private function isDomElement( mixed $node, string $tagName ): bool {
		return $node instanceof DOMElement && $tagName === $node->tagName;
	}

	/** @phpstan-assert-if-true =DOMElement $node */
	private function isTableBodyElement( mixed $node ): bool {
		return $this->isDomElement( $node, tagName: 'tbody' );
	}

	/** @phpstan-assert-if-true =DOMElement $node */
	private function isTableHeadElement( mixed $node ): bool {
		return $this->isDomElement( $node, tagName: 'th' );
	}

	/** @phpstan-assert-if-true =DOMElement $node */
	private function isTableDataElement( mixed $node ): bool {
		return $this->isDomElement( $node, tagName: 'td' );
	}

	/** @phpstan-assert-if-true =DOMElement $node */
	private function isTableRowWithTableData( DOMNode $node ): bool {
		return $node->childNodes->count() && $this->isDomElement( $node, tagName: 'tr' );
	}
}
