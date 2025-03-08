<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits;

use DOMNode;
use DOMElement;
use ArrayObject;
use DOMNodeList;
use SplFixedArray;
use TheWebSolver\Codegarage\Scraper\Helper\Marshaller;

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
	/**  @var array<string,Marshaller> */
	private array $marshallers;
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

	public function useMarshaller( Marshaller ...$marshallers ): static {
		array_walk( $marshallers, $this->registerMarshaller( ... ) );

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
		if ( empty( $this->marshallers ) ) {
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

		foreach ( $nodes as $tableBodyNode ) {
			$this->tableIds[] = $id = spl_object_id( $tableBodyNode );

			foreach ( $tableBodyNode->childNodes as $contentNode ) {
				$this->isDomElement( $contentNode, tagName: 'tr' )
					&& ( $this->scanTableHead( $contentNode->childNodes, tableId: $id )
						|| $this->scanTableData( $contentNode->childNodes, tableId: $id ) );
			}
		}
	}

	/** @param DOMNodeList<DOMNode> $nodes */
	protected function scanTableHead( DOMNodeList $nodes, int $tableId ): bool {
		if ( ! $marshaller = ( $this->marshallers['th'] ?? null ) ) {
			return false;
		}

		if ( ! $nodesArray = $nodes->count() ? iterator_to_array( $nodes ) : array() ) {
			return false;
		}

		if ( ! $heads = array_filter( $nodesArray, $this->isTableHeadElement( ... ) ) ) {
			return false;
		}

		$heads                            = array_map( $marshaller->collect( ... ), $heads );
		$this->tableHeadNames[ $tableId ] = SplFixedArray::fromArray( $names = $marshaller->content() );
		$this->tableHeads[ $tableId ]     = new ArrayObject( $this->onlyContents ? $names : $heads );

		$marshaller->reset();

		return true;
	}

	/** @param DOMNodeList<DOMNode> $nodes */
	protected function scanTableData( DOMNodeList $nodes, int $tableId ): bool {
		if ( ! $marshaller = ( $this->marshallers['td'] ?? null ) ) {
			return false;
		}

		if ( ! $nodesArray = $nodes->count() ? iterator_to_array( $nodes ) : array() ) {
			return false;
		}

		if ( ! $data = array_filter( $nodesArray, $this->isTableDataElement( ... ) ) ) {
			return false;
		}

		$this->tableRows[ $tableId ] = new ArrayObject( $this->tableDataSet( $data, $marshaller, $tableId ) );

		return true;
	}

	/**
	 * @param DOMElement[] $elements
	 * @return array<string|int,string|array{0:string,1?:string,2?:DOMElement}>
	 */
	private function tableDataSet( array $elements, Marshaller $marshaller, int $tableId ): array {
		$toCollect = $marshaller->collectables()['onlyContent'];
		$data      = array();

		$marshaller->onlyContent( $this->onlyContents );

		foreach ( $elements as $tableData ) {
			$data[] = $marshaller->collect( $tableData );

			$tableData->childElementCount && ! $this->onlyContents
				&& $this->scanTableBodyNodeIn( $tableData->childNodes );
		}

		$this->onlyContents && ( $data = $marshaller->content() );

		/** @var string[] $heads */
		$heads = ( $names = ( $this->tableHeadNames[ $tableId ] ?? null ) ) ? $names->toArray() : array();

		$marshaller->onlyContent( $toCollect )->reset();

		return $heads && count( $heads ) === count( $data ) ? array_combine( $heads, $data ) : $data;
	}

	private function registerMarshaller( Marshaller $marshaller ): void {
		$this->marshallers[ $marshaller->tagName ] = $marshaller;
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
}
