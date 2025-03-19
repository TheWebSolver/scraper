<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits;

use DOMNode;
use Iterator;
use DOMElement;
use ArrayObject;
use DOMNodeList;
use SplFixedArray;
use TheWebSolver\Codegarage\Scraper\AssertDOMElement;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

/**
 * @template ThReturn
 * @template TdReturn
 */
trait TableNodeAware {
	private bool $scanAllTables = false;
	/** @var int[] */
	private array $tableIds = array();
	/** @var array<int,ArrayObject<int,ThReturn>> */
	private array $tableHeads;
	/** @var array<int,array<int,ArrayObject<array-key,TdReturn>>> */
	private array $tableRows = array();
	/** @var array<int,SplFixedArray<string>> */
	private array $tableHeadNames = array();
	/** @var array{tr?:Transformer<ArrayObject<array-key,TdReturn>|DOMElement>,th?:Transformer<ThReturn>,td?:Transformer<TdReturn>} */
	private array $transformers;

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

	/** @return ($namesOnly is true ? array<int,SplFixedArray<string>> : array<int,ArrayObject<int,ThReturn>>) */
	public function getTableHead( bool $namesOnly = false ): array {
		return $namesOnly ? $this->tableHeadNames : $this->tableHeads;
	}

	/** @return array<int,array<int,ArrayObject<array-key,TdReturn>>> */
	public function getTableData(): array {
		return $this->tableRows;
	}

	/**
	 * @param array{
	 *   tr ?: Transformer<ArrayObject<array-key,TdReturn>|DOMElement>,
	 *   th ?: Transformer<ThReturn>,
	 *   td ?: Transformer<TdReturn>
	 * } $transformers
	 */
	public function useTransformers( array $transformers ): static {
		$this->transformers = $transformers;

		return $this;
	}

	public function withAllTableNodes( bool $scan = true ): static {
		$this->scanAllTables = $scan;

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
	final protected function isNodeTRWithTDContent( DOMNode $node ): bool {
		return $node->childNodes->count() && AssertDOMElement::isValid( $node, type: 'tr' );
	}

	/** @phpstan-assert-if-true =DOMElement $node */
	final protected function isNodeTHorTD( DOMNode $node ): bool {
		return $node instanceof DOMElement && in_array( $node->tagName, array( 'th', 'td' ), strict: true );
	}

	/** @return ?array<int,string> */
	protected function scanTableHead( DOMNode $node, int $tableId ): ?array {
		$thTransformer = $this->transformers['th'] ?? null;
		$collection    = new ArrayObject();
		$names         = array();

		foreach ( $node->childNodes as $node ) {
			if ( ! AssertDOMElement::isValid( $node, type: 'th' ) ) {
				continue;
			}

			$trimmed = trim( $node->textContent );
			$content = $thTransformer?->transform( $node ) ?? $trimmed;
			$names[] = is_string( $content ) ? $content : $trimmed;

			$collection->append( $content );

		}

		if ( ! $collection->count() ) {
			return null;
		}

		$this->setTableId( $tableId );

		$this->tableHeadNames[ $tableId ] = SplFixedArray::fromArray( $names );
		$this->tableHeads[ $tableId ]     = $collection;

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
		if ( AssertDOMElement::isValid( $tableIterator->current(), type: 'caption' ) ) {
			$tableIterator->next();
		}

		if ( $head = $this->tableHeadContentFrom( $tableIterator->current(), $tableId ) ) {
			$tableIterator->next();
		}

		while ( ! $body && $tableIterator->valid() ) {
			AssertDOMElement::isValid( $node = $tableIterator->current(), type: 'tbody' ) && $body = $node;

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
	 * @param DOMElement $tableRow HTML Table Row element containing Table Data.
	 * @param ?string[]  $keys     Corresponding index keys for each Table Data.
	 * @return TdReturn[]
	 */
	protected function tableDataSet( DOMElement $tableRow, ?array $keys ): array {
		$data          = array();
		$tdTransformer = $this->transformers['td'] ?? null;

		foreach ( $tableRow->childNodes as $node ) {
			// Skip if not a <th> or <td>. Possibly is a comment node. Other nodes shouldn't even be here.
			if ( ! $this->isNodeTHorTD( $node ) ) {
				continue;
			}

			$data[] = $tdTransformer?->transform( $node ) ?? trim( $node->textContent );

			$node->hasChildNodes() && $this->scanTableNodeIn( $node->childNodes );
		}

		return $keys && count( $keys ) === count( $data ) ? array_combine( $keys, $data ) : $data;
	}

	/**
	 * @param ?array<int,string> $head
	 * @param DOMElement         $body
	 * @return iterable<int,ArrayObject<array-key,TdReturn>>
	 */
	protected function fromTableContents( int $tableId, ?array $head, DOMElement $body ): iterable {
		$this->setTableId( $tableId );

		/** @var Iterator<int,DOMElement> Expected. May contain comment nodes. */
		$rowIterator    = $body->childNodes->getIterator();
		$rowTransformer = $this->transformers['tr'] ?? null;
		$head         ??= ( $headInBody = $this->scanTableHead( $rowIterator->current(), $tableId ) );

		if ( $headInBody ?? null ) {
			// We'll advance to next Table Row so that the current Table Row already collected
			// as Table Head WILL BE OMITTED and WILL NOT BE COLLECTED as a Table Data also.
			$rowIterator->next();
		}

		while ( $rowIterator->valid() ) {
			// Skip if not a <tr>. Possibly is a comment node. Other nodes shouldn't even be here.
			if ( ! AssertDOMElement::isValid( $rowIterator->current(), type: 'tr' ) ) {
				$rowIterator->next();
			}

			$current = $rowIterator->current();

			$rowIterator->next();

			$row = $rowTransformer?->transform( $current ) ?? $current;

			yield $row instanceof ArrayObject ? $row : new ArrayObject( $this->tableDataSet( $row, $head ) );
		}
	}
}
