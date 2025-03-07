<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits;

use ArrayObject;
use DOMNode;
use DOMElement;
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
	/** @var SplFixedArray<string> */
	private SplFixedArray $tableHeadNames;
	/**  @var array<string,Marshaller> */
	private array $marshallers;
	private bool $onlyContents = false;

	/** @return int[] List of scanned tables' `spl_object_id()`. */
	public function getTableIds(): array {
		return $this->tableIds;
	}

	/** @return ($namesOnly is true ? SplFixedArray<string> : array<int,ArrayObject<int,string|array{0:string,1?:string,2?:DOMElement}>>) */
	public function getTableHead( bool $namesOnly = false ): SplFixedArray|array {
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

	/** @param DomNodeList<DomNode> $nodes */
	public function scanTableBodyNodeIn( DOMNodeList $nodes ): void {
		if ( empty( $this->marshallers ) ) {
			return;
		}

		foreach ( $nodes as $node ) {
			$this->scanContentsOfTableBody( $node );
		}
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- To be used by exhibiting class.
	abstract protected function isTargetedTable( DOMElement $node ): bool;

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

	/** @param DomNodeList<DomNode> $nodes */
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

		$heads                = array_map( $marshaller->collect( ... ), $heads );
		$this->tableHeadNames = SplFixedArray::fromArray( $names = array_values( $marshaller->content() ) );

		if ( $this->onlyContents ) {
			$heads = $names;
		}

		$this->tableHeads[ $tableId ] = new ArrayObject( $heads );

		$marshaller->reset();

		return true;
	}

	/** @param DOMNodeList<DomNode> $nodes */
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

		/** @var string[] */
		$heads = isset( $this->tableHeadNames ) ? $this->tableHeadNames->toArray() : array();
		$row   = count( $heads ) === count( $data )
			? array_combine( $heads, array_map( $marshaller->collect( ... ), $data ) )
			: array_map( $marshaller->collect( ... ), $data );

		if ( $this->onlyContents ) {
			$row = count( $heads ) === count( $marshaller->content() )
				? array_combine( $heads, $marshaller->content() )
				: $marshaller->content();
		}

		$this->tableRows[ $tableId ] = new ArrayObject( $row );

		$marshaller->reset();

		return true;
	}

	private function registerMarshaller( Marshaller $marshaller ): void {
		$this->marshallers[ $marshaller->tagName ] = $marshaller;
	}

	/** @param DomNodeList<DomNode> $nodes */
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
