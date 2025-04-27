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
use TheWebSolver\Codegarage\Scraper\AssertDOMElement;
use TheWebSolver\Codegarage\Scraper\Event\TableTraced;
use TheWebSolver\Codegarage\Scraper\Data\CollectionSet;
use TheWebSolver\Codegarage\Scraper\DOMDocumentFactory;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
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

	/** @param DOMNodeList<DOMNode> $elementList */
	public function inferTableHeadFrom( iterable $elementList ): void {
		[$names, $skippedNodes, $transformer] = $this->useCurrentTableHeadDetails();

		foreach ( $elementList as $currentIndex => $node ) {
			if ( ! AssertDOMElement::isValid( $node, Table::Head ) ) {
				$this->tickCurrentHeadIterationSkippedHeadNode( $node );

				++$skippedNodes;

				continue;
			}

			$position = $currentIndex - $skippedNodes;

			$this->registerCurrentIterationTableHead( $position );

			$names[] = $transformer?->transform( $node, $this ) ?? trim( $node->textContent );
		}

		$this->registerCurrentTableHead( $names );
	}

	/** @param iterable<array-key,string|DOMNode> $elementList */
	public function inferTableDataFrom( iterable $elementList ): array {
		$data = [];

		[$keys, $offset, $lastPosition, $skippedNodes, $transformer] = $this->useCurrentTableColumnDetails();

		foreach ( $elementList as $currentIndex => $node ) {
			if ( ! $this->isTableColumnStructure( $node ) ) {
				++$skippedNodes;

				continue;
			}

			$this->assertCurrentColumnIsDOMElement( $node );

			$currentPosition = $currentIndex - $skippedNodes;

			if ( isset( $offset[ $currentPosition ] ) ) {
				continue;
			}

			if ( $this->hasColumnReachedAtLastPosition( $currentPosition, $lastPosition ) ) {
				break;
			}

			$this->registerCurrentIterationTableColumn( $keys[ $currentPosition ] ?? null, $currentPosition + 1 );

			$this->registerCurrentTableColumn( $node, $transformer, $data )
				&& $this->findTableStructureIn( $node, minChildNodesCount: 1 );

			unset( $this->currentIteration__columnName );
		}//end foreach

		return $data;
	}

	private function inferChildNodesFromTable( DOMElement $element ): bool {
		$table = $this->ensureTableWithChildNodesStructure( $element );

		if ( ! $table || ! $tableStructure = $this->traceTableStructureIn( $table ) ) {
			return false;
		}

		[$bodyNode, $captionNode, $headNode] = $tableStructure;

		$splId = spl_object_id( $element );
		$id    = $splId * spl_object_id( $bodyNode );

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

			if ( $this->discoveredTargetedTable( $node ) ) {
				break;
			}
		}
	}

	private function findTableStructureIn( DOMNode $node, int $minChildNodesCount = 0 ): void {
		( ! $this->getTableId() || $this->shouldPerform__allTableDiscovery )
			&& ( ( $nodes = $node->childNodes )->length > $minChildNodesCount )
			&& $this->inferTableFromDOMNodeList( $nodes );
	}

	/** @phpstan-assert-if-true =DOMElement $node */
	private function isTableRowStructure( DOMNode $node ): bool {
		return $node->childNodes->length && AssertDOMElement::isValid( $node, Table::Row );
	}

	/**
	 * @phpstan-assert DOMElement $node
	 * @throws InvalidSource When $node is not a DOMElement.
	 */
	private function assertCurrentColumnIsDOMElement( mixed $node ): void {
		$node instanceof DOMElement || throw new InvalidSource(
			sprintf(
				'Unsupported node type: "%1$s" provided when using trait "%2$s".',
				get_debug_type( $node ),
				HtmlTableFromNode::class
			)
		);
	}

	private function ensureTableWithChildNodesStructure( DOMElement $element ): ?DOMElement {
		if ( 'table' !== $element->tagName ) {
			$this->findTableStructureIn( $element );

			return null;
		}

		return $this->isTargetedTable( $element ) && $element->childNodes->length ? $element : null;
	}

	private function captionStructureContentFrom( DOMElement $node ): void {
		$this->dispatchEvent( new TableTraced( Table::Caption, EventAt::Start, $node, $this ) );

		$transformer = $this->discoveredTable__transformers['caption'] ?? null;
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

		[$headInspected, $bodyStarted, $position, $transformer] = $this->useCurrentTableBodyDetails();

		foreach ( $rowList as $row ) {
			$isHead        = ! $headInspected && $this->inspectFirstRowForHeadStructure( $row );
			$headInspected = true;

			// Contents of <tr> as head MUST NOT BE COLLECTED as table column also.
			// Advance table body to next <tr> if first row is collected as head.
			if ( $isHead ) {
				// We can only determine whether first row contains table heads after it is inferred.
				// We'll simply dispatch the ending event here to notify subscribers, if any.
				$this->dispatchEvent( new TableTraced( Table::THead, EventAt::End, $row, $this ) );

				continue;
			}

			if ( ! $bodyStarted ) {
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

			$content = $transformer?->transform( $row, $this ) ?? $row->childNodes;

			match ( true ) {
				$content instanceof CollectionSet => yield $content->key => $content->value,
				$content instanceof ArrayObject   => yield $content,
				default                           => yield new ArrayObject( $this->inferTableDataFrom( $content ) ),
			};

			$this->registerCurrentIterationTableRow( ++$position );
		}//end foreach

		$this->dispatchEvent( new TableTraced( Table::Row, EventAt::End, $body, $this ) );
	}

	private function inspectFirstRowForHeadStructure( DOMElement $row ): bool {
		$this->inferTableHeadFrom( $row->childNodes );

		return $this->currentIteration__allTableHeads;
	}

	private function discoveredTargetedTable( mixed $node ): bool {
		return ! $this->shouldPerform__allTableDiscovery
			&& AssertDOMElement::isValid( $node )
			&& $this->isTargetedTable( $node );
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
}
