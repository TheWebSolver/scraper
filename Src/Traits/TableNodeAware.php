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
			$id                     = spl_object_id( $node );
			$this->tableRows[ $id ] = iterator_to_array( $this->scanTableContents( $node, $id ) );
		}
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- To be used by exhibiting class.
	protected function isTargetedTable( DOMElement $node ): bool {
		return true;
	}

	/** @return iterable<int,ArrayObject<array-key,string|array{0:string,1?:string,2?:DOMElement}>> */
	protected function scanTableContents( DOMNode $node, int $tableId ): iterable {
		if ( ! $content = $this->tableContentFrom( $node, $tableId ) ) {
			return;
		}

		[$tableHead, $tableBody] = $content;

		if ( ! $tableBody ) {
			return;
		}

		$this->tableIds[] = $tableId;
		$rowMarshaller    = $this->transformers['tr'] ?? null;

		/** @var Iterator<int,DOMElement> Expected. May contain comment nodes. */
		$rowIterator = $tableBody->childNodes->getIterator();
		$tableHead ??= ( $tableHeadInBody = $this->scanTableHead( $rowIterator->current(), $tableId ) );

		if ( $tableHeadInBody ?? null ) {
			// We'll advance to next Table Row so that the current Table Row already collected
			// as Table Head MUST BE OMITTED and MUST NOT BE COLLECTED as a Table Data also.
			$rowIterator->next();
		}

		while ( $rowIterator->valid() ) {
			$current  = $rowIterator->current();
			$tableRow = $rowMarshaller?->collect( $current, onlyContent: true )
				?? Normalize::nodesToArray( $current->childNodes );

			$rowIterator->next();

			if ( $tableRow && '#comment' !== $current->nodeName ) {
				yield new ArrayObject( $this->tableDataSet( $tableRow, $tableHead ) );
			}
		}
	}

	/** @return ?array<int,string> */
	protected function scanTableHead( DOMNode $node, int $tableId ): ?array {
		if ( ! $marshaller = ( $this->transformers['th'] ?? null ) ) {
			return null;
		}

		$nodes = $node->childNodes;
		$heads = array_filter( Normalize::nodesToArray( $nodes ), $this->isTableHeadElement( ... ) );

		if ( count( $heads ) !== $nodes->length ) {
			return null;
		}

		/** @var bool[] */
		$contentsOnly                     = array_pad( array(), count( $heads ), $this->onlyContents );
		$heads                            = array_map( $marshaller->collect( ... ), $heads, $contentsOnly );
		$this->tableHeadNames[ $tableId ] = SplFixedArray::fromArray( $content = $marshaller->getContent() );
		$this->tableHeads[ $tableId ]     = new ArrayObject( $heads );

		$marshaller->flushContent();

		return $content;
	}

	/** @return ?array{0:?array<int,string>,1:?DOMElement} */
	private function tableContentFrom( DOMNode $node, int $tableId ): ?array {
		if ( ! $this->isDomElement( $node, tagName: 'table' ) ) {
			$this->scanTableNodeIn( $node->childNodes );

			return null;
		}

		if ( ! $this->isTargetedTable( $node ) ) {
			return null;
		}

		/** @var Iterator<int,DOMNode> */
		$tableIterator = $node->childNodes->getIterator();

		if ( 'caption' === $tableIterator->current()->nodeName ) {
			// Skip scraping content for Table Caption <caption>.
			$tableIterator->next();
		}

		if ( $tableHead = $this->tableHeadContentFrom( $tableIterator->current(), $tableId ) ) {
			$tableIterator->next();
		}

		$tableBody = null;

		while ( ! $tableBody && $tableIterator->valid() ) {
			$tableBody = $this->isTableBodyElement( $current = $tableIterator->current() ) ? $current : null;

			$tableIterator->next();
		}

		return array( $tableHead, $tableBody );
	}

	/** @return ?array<int,string> */
	protected function tableHeadContentFrom( DOMNode $node, int $tableId ): ?array {
		if ( 'thead' !== $node->nodeName ) {
			return null;
		}

		/** @var Iterator<int,DOMNode> */
		$headIterator = $node->childNodes->getIterator();
		$headerRow    = null;

		while ( ! $headerRow && $headIterator->valid() ) {
			$this->isDomElement( $node = $headIterator->current(), tagName: 'tr' ) && $headerRow = $node;

			$headIterator->next();
		}

		return $headerRow ? $this->scanTableHead( $headerRow, $tableId ) : null;
	}

	/**
	 * @param DOMNode[]          $tableRow
	 * @param ?array<int,string> $tableHead
	 * @return array<string|int,string|array{0:string,1?:string,2?:DOMElement}>
	 */
	private function tableDataSet( array $tableRow, ?array $tableHead ): array {
		$data       = array();
		$marshaller = $this->transformers['td'] ?? null;

		foreach ( $tableRow as $tableData ) {
			// If not "td", must be a HTML Node with "#comment" as nodeName. Other nodes shouldn't even be here.
			if ( ! $tableData instanceof DOMElement || 'td' !== $tableData->nodeName ) {
				continue;
			}

			$data[] = $marshaller?->collect( $tableData, $this->onlyContents ) ?? trim( $tableData->textContent );

			$tableData->childElementCount && ! $this->onlyContents
				&& $this->scanTableBodyNodeIn( $tableData->childNodes );
		}

		$marshaller?->flushContent();

		return $tableHead && count( $tableHead ) === count( $data ) ? array_combine( $tableHead, $data ) : $data;
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
