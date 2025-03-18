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
	public function scanTableNodeIn( DOMNodeList $nodes ): void {
		foreach ( $nodes as $node ) {
			if ( $contents = $this->validateContentsOf( $node, $id = spl_object_id( $node ) ) ) {
				$this->tableRows[ $id ] = iterator_to_array( $this->fromTableContents( $id, ...$contents ) );
			}
		}
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- To be used by exhibiting class.
	protected function isTargetedTable( DOMElement $node ): bool {
		return true;
	}

	final protected function setTableId( int $id ): void {
		if ( ! in_array( $id, $this->tableIds, true ) ) {
			$this->tableIds[] = $id;
		}
	}

	/** @param DOMNodeList<DOMNode> $nodes */
	final protected function findTableNodeIn( DOMNodeList $nodes ): void {
		( ! $this->tableIds || $this->scanAllTables )
			&& $nodes->count() && $this->scanTableNodeIn( $nodes );
	}

	/** @phpstan-assert-if-true =DOMElement $node */
	final protected function isDomElement( mixed $node, string $tagName ): bool {
		return $node instanceof DOMElement && $tagName === $node->tagName;
	}

	/** @phpstan-assert-if-true =DOMElement $node */
	final protected function isNodeTRWithTDContent( DOMNode $node ): bool {
		return $node->childNodes->count() && $this->isDomElement( $node, tagName: 'tr' );
	}

	/** @phpstan-assert-if-true =DOMElement $node */
	final protected function isNodeTHorTD( DOMNode $node ): bool {
		return $node instanceof DOMElement && in_array( $node->tagName, array( 'th', 'td' ), strict: true );
	}

	/** @return ?array<int,string> */
	protected function scanTableHead( DOMNode $node, int $tableId ): ?array {
		$marshaller = $this->transformers['th'] ?? null;
		$collection = new ArrayObject();

		foreach ( $node->childNodes as $node ) {
			$this->isDomElement( $node, 'th' ) && $collection->append(
				$marshaller?->collect( $node, $this->onlyContents ) ?? trim( $node->textContent )
			);
		}

		if ( ! $collection->count() ) {
			return null;
		}

		$this->setTableId( $tableId );

		$content                          = $marshaller?->getContent() ?? $collection->getArrayCopy();
		$this->tableHeadNames[ $tableId ] = SplFixedArray::fromArray( $content );
		$this->tableHeads[ $tableId ]     = $collection;

		$marshaller?->flushContent();

		return $content;
	}

	/** @return ?Iterator<int,DOMNode> */
	protected function fromTargetedHtmlTable( DOMNode $node ): ?Iterator {
		if ( ! $this->isDomElement( $node, tagName: 'table' ) ) {
			$this->findTableNodeIn( $node->childNodes );

			return null;
		}

		/** @var ?Iterator<int,DOMNode> */
		return $this->isTargetedTable( $node ) && $node->hasChildNodes()
			? $node->childNodes->getIterator()
			: null;
	}

	/** @return ?array<int,string> */
	protected function tableHeadContentFrom( DOMNode $node, int $tableId, ?DOMElement $row = null ): ?array {
		if ( 'thead' !== $node->nodeName ) {
			return null;
		}

		/** @var Iterator<int,DOMNode> */
		$headIterator = $node->childNodes->getIterator();

		while ( ! $row && $headIterator->valid() ) {
			$this->isNodeTRWithTDContent( $node = $headIterator->current() ) && $row = $node;

			$headIterator->next();
		}

		return $row ? $this->scanTableHead( $row, $tableId ) : null;
	}

	/** @return ?array{0:?array<int,string>,1:?DOMElement} */
	protected function tableContentFrom( DOMNode $node, int $tableId, ?DOMElement $body = null ): ?array {
		if ( ! $tableIterator = $this->fromTargetedHtmlTable( $node ) ) {
			return null;
		}

		// Currently, <caption> element is skipped.
		if ( $this->isDomElement( $tableIterator->current(), tagName: 'caption' ) ) {
			$tableIterator->next();
		}

		if ( $head = $this->tableHeadContentFrom( $tableIterator->current(), $tableId ) ) {
			$tableIterator->next();
		}

		while ( ! $body && $tableIterator->valid() ) {
			$this->isDomElement( $node = $tableIterator->current(), tagName: 'tbody' ) && $body = $node;

			$tableIterator->next();
		}

		return array( $head, $body );
	}

	/** @return ?array{0:?array<int,string>,1:DOMElement} */
	protected function validateContentsOf( DOMNode $node, int $tableId ): ?array {
		$content = $this->tableContentFrom( $node, $tableId );

		return ! $content || ! $content[1] ? null : $content;
	}

	/**
	 * @param DOMNode[]          $tableRow
	 * @param ?array<int,string> $tableHead
	 * @return array<string|int,string|array{0:string,1?:string,2?:DOMElement}>
	 */
	protected function tableDataSet( array $tableRow, ?array $tableHead ): array {
		$data       = array();
		$marshaller = $this->transformers['td'] ?? null;

		foreach ( $tableRow as $tableData ) {
			// If not "th" or "td", must be a comment Node. Other nodes shouldn't even be here.
			if ( ! $this->isNodeTHorTD( $tableData ) ) {
				continue;
			}

			$data[] = $marshaller?->collect( $tableData, $this->onlyContents ) ?? trim( $tableData->textContent );

			$tableData->hasChildNodes() && ! $this->onlyContents
				&& $this->scanTableNodeIn( $tableData->childNodes );
		}

		$marshaller?->flushContent();

		return $tableHead && count( $tableHead ) === count( $data ) ? array_combine( $tableHead, $data ) : $data;
	}

	/**
	 * @param ?array<int,string> $head
	 * @param DOMElement         $body
	 * @return iterable<int,ArrayObject<array-key,string|array{0:string,1?:string,2?:DOMElement}>>
	 */
	protected function fromTableContents( int $tableId, ?array $head, DOMElement $body ): iterable {
		$this->setTableId( $tableId );

		/** @var Iterator<int,DOMElement> Expected. May contain comment nodes. */
		$rowIterator   = $body->childNodes->getIterator();
		$rowMarshaller = $this->transformers['tr'] ?? null;
		$head        ??= ( $headInBody = $this->scanTableHead( $rowIterator->current(), $tableId ) );

		if ( $headInBody ?? null ) {
			// We'll advance to next Table Row so that the current Table Row already collected
			// as Table Head WILL BE OMITTED and WILL NOT BE COLLECTED as a Table Data also.
			$rowIterator->next();
		}

		while ( $rowIterator->valid() ) {
			$current = $rowIterator->current();
			$row     = $rowMarshaller?->collect( $current, onlyContent: true )
				?? Normalize::nodesToArray( $current->childNodes );

			$rowIterator->next();

			if ( $row && '#comment' !== $current->nodeName ) {
				yield new ArrayObject( $this->tableDataSet( $row, $head ) );
			}
		}
	}
}
