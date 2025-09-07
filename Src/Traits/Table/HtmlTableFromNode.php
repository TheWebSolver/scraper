<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits\Table;

use DOMNode;
use Iterator;
use DOMElement;
use ArrayObject;
use DOMNodeList;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Enums\EventAt;
use TheWebSolver\Codegarage\Scraper\Data\TableCell;
use TheWebSolver\Codegarage\Scraper\Data\TableHead;
use TheWebSolver\Codegarage\Scraper\AssertDOMElement;
use TheWebSolver\Codegarage\Scraper\Event\TableTraced;
use TheWebSolver\Codegarage\Scraper\Data\CollectionSet;
use TheWebSolver\Codegarage\Scraper\DOMDocumentFactory;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Traits\Table\TableExtractor;

/** @template TColumnReturn */
trait HtmlTableFromNode {
	/** @use TableExtractor<TColumnReturn> */
	use TableExtractor;

	/** @throws InvalidSource When "table" cannot be resolved in given source. */
	public function inferTableFrom( string|DOMElement $source, bool $normalize = true ): void {
		$source = $this->getValidatedTableSource( $source, $normalize );

		if ( $source instanceof DOMNodeList ) {
			$this->inferTableFromDOMNodeList( $source );

			return;
		}

		$this->inferChildNodesFromTable( $source );
	}

	protected function useCurrentIterationValidatedHead( mixed $node ): TableHead {
		if ( $isValid = AssertDOMElement::isValid( $node, Table::Head ) ) {
			return new TableHead( $isValid, isAllowed: true, value: trim( $node->textContent ) );
		}

		$isAllowed = $node instanceof DOMNode && XML_COMMENT_NODE === $node->nodeType;

		return new TableHead( $isValid, $isAllowed, value: null );
	}

	/** @param Transformer<static,string> $transformer */
	protected function transformCurrentIterationTableHead( mixed $node, Transformer $transformer ): string {
		return $transformer->transform( $this->assertThingIsValidNode( $node ), $this );
	}

	protected function getTagnameFrom( mixed $thing ): mixed {
		return $thing instanceof DOMElement ? $thing->tagName : null;
	}

	/**
	 * @param Transformer<static,TColumnReturn> $transformer
	 * @return TableCell<TColumnReturn>
	 */
	protected function transformCurrentIterationTableColumn( mixed $node, Transformer $transformer ): TableCell {
		return new TableCell(
			position: 0,
			value: $transformer->transform( $column = $this->assertThingIsValidNode( $node ), $this ),
			rowspan: (int) ( $column->getAttribute( 'rowspan' ) ?: 1 )
		);
	}

	/** @param ?TColumnReturn $value */
	protected function afterCurrentTableColumnRegistered( mixed $column, mixed $value ): void {
		$column = $this->assertThingIsValidNode( $column );

		$value && $this->findTableStructureIn( node: $column, minChildNodesCount: 1 );
	}

	private function inferChildNodesFromTable( DOMElement $element ): bool {
		$table = $this->ensureTableWithChildNodesStructure( $element );

		if ( ! $table || ! $tableStructure = $this->traceTableStructureIn( $table ) ) {
			return false;
		}

		[$bodyNode, $captionNode, $headNode] = $tableStructure;
		$id                                  = spl_object_id( $element ) * spl_object_id( $bodyNode );

		$this->dispatchEventForTable( $id, $bodyNode );

		$captionNode && $this->captionStructureContentFrom( $captionNode );
		$headNode && $this->headStructureContentFrom( $headNode );

		$iterator = $this->bodyStructureIteratorFrom( $bodyNode );

		$iterator->valid() && ( $this->discoveredTable__rows[ $id ] = $iterator );

		$this->dispatchEvent( new TableTraced( Table::TBody, EventAt::End, $element, $this ) );

		return true;
	}

	/** @param DOMNodeList<DOMNode> $elementList */
	private function inferTableFromDOMNodeList( DOMNodeList $elementList ): void {
		foreach ( $elementList as $node ) {
			if ( ! AssertDOMElement::isValid( $node ) || ! $this->inferChildNodesFromTable( $node ) ) {
				continue;
			}

			if ( $this->targetIsCurrentTable( $node ) ) {
				break;
			}
		}
	}

	private function findTableStructureIn( DOMElement $node, int $minChildNodesCount = 0 ): void {
		( ! $this->getTableId() || $this->shouldPerform__allTableDiscovery )
			&& ( ( $nodes = $node->childNodes )->length > $minChildNodesCount )
			&& $this->inferTableFromDOMNodeList( $nodes );
	}

	/** @phpstan-assert-if-true =DOMElement $node */
	private function isTableRowStructure( DOMNode $node ): bool {
		return $node->childNodes->length && AssertDOMElement::isValid( $node, Table::Row );
	}

	private function ensureTableWithChildNodesStructure( DOMElement $element ): ?DOMElement {
		if ( 'table' !== $element->tagName ) {
			$this->findTableStructureIn( $element );

			return null;
		}

		if ( $isTarget = $this->isTargetedTable( $element ) ) {
			$this->registerTargetedTable( $element );
		}

		return $isTarget && $element->childNodes->length ? $element : null;
	}

	private function captionStructureContentFrom( DOMElement $node ): void {
		$this->dispatchEvent( new TableTraced( Table::Caption, EventAt::Start, $node, $this ) );

		$transformer = $this->discoveredTable__transformers[ Table::Caption->value ] ?? null;
		$content     = $transformer?->transform( $node, $this ) ?? trim( $node->textContent );

		$this->discoveredTable__captions[ $this->currentTable__id ] = $content;

		$this->dispatchEvent( new TableTraced( Table::Caption, EventAt::End, $node, $this ) );
	}

	private function headStructureContentFrom( DOMElement $node ): void {
		$this->dispatchEvent( $event = new TableTraced( Table::THead, EventAt::Start, $node, $this ) );

		if ( $event->shouldStopTrace() ) {
			$this->dispatchEvent( new TableTraced( Table::THead, EventAt::End, $node, $this ) );

			return;
		}

		$headRow = $node->getElementsByTagName( Table::Head->value );

		$headRow->length && $this->inferTableHeadFrom( $headRow );

		$this->dispatchEvent( new TableTraced( Table::THead, EventAt::End, $node, $this ) );
	}

	/** @return ?array{0:DOMElement,1:?DOMElement,2:?DOMElement} */
	private function traceTableStructureIn( DOMElement $element ): ?array {
		if ( ! $bodyNode = $element->getElementsByTagName( Table::TBody->value )->item( 0 ) ) {
			return null;
		}

		if ( ! $this->tableColumnsExistInBody( $bodyNode ) ) {
			return null;
		}

		$captionNode = $element->getElementsByTagName( Table::Caption->value )->item( 0 );
		$headNode    = $element->getElementsByTagName( Table::THead->value )->item( 0 );

		return [
			$bodyNode,
			$this->shouldTraceTableStructure( Table::Caption ) ? $captionNode : null,
			$this->shouldTraceTableStructure( Table::THead ) ? $headNode : null,
		];
	}

	/**
	 * @param DOMElement $body
	 * @return Iterator<array-key,ArrayObject<array-key,TColumnReturn>>
	 */
	private function bodyStructureIteratorFrom( DOMElement $body ): Iterator {
		if ( ! ( $rowList = $body->getElementsByTagName( Table::Row->value ) )->length ) {
			return;
		}

		[$headInspected, $bodyStarted, $position] = $this->useCurrentTableBodyDetails();

		foreach ( $rowList as $row ) {
			$isHead        = ! $headInspected && $this->inspectFirstRowForHeadStructure( $row );
			$headInspected = true;

			if ( $isHead ) {
				// We can only determine whether first row contains table heads after it is inferred.
				// We'll simply dispatch the ending event here to notify subscribers, if any.
				$this->dispatchEvent( new TableTraced( Table::THead, EventAt::End, $row, $this ) );

				// Contents of <tr> as head MUST NOT BE COLLECTED as table column also.
				// Advance table body to next <tr> if first row is collected as head.
				continue;
			}

			if ( ! $bodyStarted ) {
				$this->hydrateIndicesSourceFromAttribute();
				$this->dispatchEvent( $event = new TableTraced( Table::Row, EventAt::Start, $body, $this ) );

				// Although not recommended, it is entirely possible to stop inferring further table rows.
				// This just means that tracer was used to trace "<th>" that were present in "<tbody>".
				if ( $event->shouldStopTrace() ) {
					break;
				}

				$bodyStarted = true;
			}

			// TODO: add support whether to skip yielding empty <tr> or not.
			if ( ! trim( $row->textContent ) ) {
				continue;
			}

			$transformer = $this->discoveredTable__transformers[ Table::Row->value ] ?? null;
			$content     = $transformer?->transform( $row, $this ) ?? $row->childNodes;

			if ( $content instanceof CollectionSet ) {
				$this->registerCurrentTableDatasetCount( $content->value->count() );

				yield $content->key => $content->value;
			} else {
				$dataset = $content instanceof ArrayObject ? $content : new ArrayObject( $this->inferTableDataFrom( $content ) );

				$this->registerCurrentTableDatasetCount( $dataset->count() );

				yield $dataset;
			}

			$this->registerCurrentIterationTableRow( ++$position );
		}//end foreach

		$this->dispatchEvent( new TableTraced( Table::Row, EventAt::End, $body, $this ) );
	}

	private function inspectFirstRowForHeadStructure( DOMElement $row ): bool {
		$this->inferTableHeadFrom( $row->childNodes );

		return $this->currentIteration__allTableHeads;
	}

	private function tableColumnsExistInBody( DOMElement $body ): bool {
		return ! ! $body->getElementsByTagName( 'td' )->length;
	}

	/**
	 * @return DOMElement|DOMNodeList<DOMNode>
	 * @throws InvalidSource When source invalid.
	 */
	private function getValidatedTableSource( string|DOMElement $source, bool $normalize ): DOMElement|DOMNodeList {
		if ( ! $source instanceof DOMElement ) {
			return DOMDocumentFactory::bodyFromHtml( $source, normalize: $normalize )->childNodes;
		}

		'table' !== $source->tagName && throw new InvalidSource(
			sprintf( '%s trait only supports table "DOMElement"', HtmlTableFromNode::class )
		);

		return $source;
	}

	/** @phpstan-assert DOMElement $node */
	private function assertThingIsValidNode( mixed $node ): DOMElement {
		return $node instanceof DOMElement ? $node : $this->throwUnsupportedNodeType( $node, HtmlTableFromNode::class );
	}
}
