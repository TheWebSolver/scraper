<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits;

use DOMNode;
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
	/** @var array<ArrayObject<array-key,string|array{0:string,1?:string,2?:DOMElement}>> */
	private array $tableRows = array();
	/** @var array<int,SplFixedArray<string>> */
	private array $tableHeadNames = array();
	/**  @var array{tr?:Transformer<?DOMNode[]>,th?:Transformer<string>,td?:Transformer<string>} */
	private array $transformers;
	private bool $onlyContents = false;

	/** @return int[] List of scanned tables' `spl_object_id()`. */
	public function getTableIds(): array {
		return $this->tableIds;
	}

	/**
	 * @return ($namesOnly is true
	 *   ? array<int,SplFixedArray<string>>
	 *   : array<int,ArrayObject<int,string|array{0:string,1?:string,2?:DOMElement}>>)
	 */
	public function getTableHead( bool $namesOnly = false ): array {
		return $namesOnly ? $this->tableHeadNames : $this->tableHeads;
	}

	/** @return array<ArrayObject<array-key,string|array{0:string,1?:string,2?:DOMElement}>> */
	public function getTableData(): array {
		return $this->tableRows;
	}

	/** @param array{tr?:Transformer<?DOMNode[]>,th?:Transformer<string>,td?:Transformer<string>} $transformers */
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
		if ( empty( $this->transformers ) ) {
			return;
		}

		foreach ( $nodes as $node ) {
			$this->scanContentsOfTableBody( $node );
		}
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- To be used by exhibiting class.
	protected function isTargetedTable( DOMElement $node ): bool {
		return true;
	}

	protected function scanContentsOfTableBody( DOMNode $node ): void {
		if ( ! $this->isDomElement( $node, tagName: 'table' ) ) {
			$this->scanForTableBodyNodeIn( $node->childNodes );

			return;
		}

		if ( ! $this->isTargetedTable( $node ) ) {
			return;
		}

		/** @var DOMElement[] */
		$nodes = array_filter( iterator_to_array( $node->childNodes ), $this->isTableBodyElement( ... ) );

		if ( empty( $nodes ) ) {
			return;
		}

		$rowMarshaller = $this->transformers['tr'] ?? null;

		foreach ( $nodes as $tableBodyNode ) {
			$this->tableIds[] = $tableId = spl_object_id( $tableBodyNode );

			foreach ( $tableBodyNode->childNodes as $tableRow ) {
				if ( ! $this->isTableRowWithTableData( $tableRow ) ) {
					continue;
				}

				$nodes = $rowMarshaller?->collect( $tableRow, onlyContent: true )
					?? Normalize::nodesToArray( $tableRow->childNodes );

				$nodes && ( $this->scanTableHead( $nodes, $tableId ) || $this->scanTableData( $nodes, $tableId ) );
			}
		}
	}

	/** @param DOMNode[] $nodes */
	protected function scanTableHead( array $nodes, int $tableId ): bool {
		if ( ! $marshaller = ( $this->transformers['th'] ?? null ) ) {
			return false;
		}

		if ( ! $heads = array_filter( $nodes, $this->isTableHeadElement( ... ) ) ) {
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
		if ( ! $marshaller = ( $this->transformers['td'] ?? null ) ) {
			return false;
		}

		if ( ! $data = array_filter( $nodes, $this->isTableDataElement( ... ) ) ) {
			return false;
		}

		$this->tableRows[ $tableId ] = new ArrayObject( $this->tableDataSet( $data, $marshaller, $tableId ) );

		return true;
	}

	/**
	 * @param DOMElement[]        $elements
	 * @param Transformer<string> $marshaller
	 * @return array<string|int,string|array{0:string,1?:string,2?:DOMElement}>
	 */
	private function tableDataSet( array $elements, Transformer $marshaller, int $tableId ): array {
		$data = array();

		foreach ( $elements as $tableData ) {
			$data[] = $marshaller->collect( $tableData, $this->onlyContents );

			$tableData->childElementCount && ! $this->onlyContents
				&& $this->scanTableBodyNodeIn( $tableData->childNodes );
		}

		/** @var string[] $heads */
		$heads = ( $names = ( $this->tableHeadNames[ $tableId ] ?? null ) ) ? $names->toArray() : array();

		$marshaller->flushContent();

		return $heads && count( $heads ) === count( $data ) ? array_combine( $heads, $data ) : $data;
	}

	/** @param DOMNodeList<DOMNode> $nodes */
	private function scanForTableBodyNodeIn( DOMNodeList $nodes ): void {
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
