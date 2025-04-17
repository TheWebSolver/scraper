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
use TheWebSolver\Codegarage\Scraper\Data\CollectionSet;
use TheWebSolver\Codegarage\Scraper\DOMDocumentFactory;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Traits\Table\TableExtractor;

/** @template TColumnReturn */
trait HtmlTableFromNode {
	/** @use TableExtractor<TColumnReturn> */
	use TableExtractor;

	public function inferTableFrom( string $source, bool $normalize = true ): void {
		$this->inferTableFromDOMNodeList(
			DOMDocumentFactory::bodyFromHtml( $source, normalize: $normalize )->childNodes
		);
	}

	/** @param iterable<array-key,string|DOMNode> $elementList */
	public function inferTableDataFrom( iterable $elementList ): array {
		$data = array();

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

	/** @param DOMNodeList<DOMNode> $elementList */
	protected function inferTableFromDOMNodeList( DOMNodeList $elementList ): void {
		foreach ( $elementList as $node ) {
			if ( ! $tableStructure = $this->traceStructureFrom( $node ) ) {
				continue;
			}

			[$bodyNode, $captionNode, $headNode] = $tableStructure;

			$splId = spl_object_id( $node );
			$id    = $splId * spl_object_id( $bodyNode );

			$this->dispatchEventListenerForDiscoveredTable( $id, $bodyNode );

			$this->discoveredTable__captions[ $id ] = $captionNode
				? $this->captionStructureContentFrom( $captionNode )
				: null;

			$this->dispatchEventListenerForDiscoveredTableHead( $headNode );

			$iterator = $this->bodyStructureIteratorFrom( $bodyNode );

			$iterator->valid() && ( $this->discoveredTable__rows[ $id ] = $iterator );

			if ( $this->discoveredTargetedTable( $node ) ) {
				break;
			}
		}//end foreach
	}

	/**
	 * Infers table head from given element list.
	 *
	 * @return ?list<string>
	 * @throws InvalidSource When element list is not DOMNodeList.
	 */
	protected function inferTableHeadFrom( DOMNode $element ): ?array {
		if ( ! AssertDOMElement::isValid( $element, Table::Row ) ) {
			return null;
		}

		[$names, $skippedNodes, $transformer] = $this->useCurrentTableHeadDetails();

		$this->fireEventListenerDispatchedFor( Table::THead, EventAt::Start, $element );

		foreach ( $element->childNodes as $currentIndex => $node ) {
			if ( ! AssertDOMElement::isValid( $node, Table::Head ) ) {
				$this->tickCurrentHeadIterationSkippedHeadNode( $node );

				++$skippedNodes;

				continue;
			}

			$position = $currentIndex - $skippedNodes;

			$this->registerCurrentIterationTableHead( $position );

			$names[] = $transformer?->transform( $node, $this ) ?? trim( $node->textContent );
		}

		return $names ?: null;
	}

	final protected function findTableStructureIn( DOMNode $node, int $minChildNodesCount = 0 ): void {
		( ! $this->getTableId() || $this->shouldPerform__allTableDiscovery )
			&& ( ( $nodes = $node->childNodes )->length > $minChildNodesCount )
			&& $this->inferTableFromDOMNodeList( $nodes );
	}

	/** @phpstan-assert-if-true =DOMElement $node */
	final protected function isTableRowStructure( DOMNode $node ): bool {
		return $node->childNodes->length && AssertDOMElement::isValid( $node, Table::Row );
	}

	/** @return Iterator<int,DOMNode> */
	private function getChildNodesIteratorFrom( DOMNode $node ): Iterator {
		return $node->childNodes->getIterator();
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

	/** @return ?Iterator<int,DOMNode> */
	private function fromCurrentStructure( DOMNode $node ): ?Iterator {
		if ( ! AssertDOMElement::isValid( $node, 'table' ) ) {
			$this->findTableStructureIn( $node );

			return null;
		}

		return $this->isTargetedTable( $node ) && $node->childNodes->length
			? $this->getChildNodesIteratorFrom( $node )
			: null;
	}

	private function toNextStructureIfInCurrentPosition( Table $target, Iterator $tableIterator ): ?DOMElement {
		if ( AssertDOMElement::isValid( $node = $tableIterator->current(), $target ) ) {
			$tableIterator->next();

			return $this->shouldTraceTableStructure( $target ) ? $node : null;
		}

		return null;
	}

	private function captionStructureContentFrom( DOMElement $node ): ?string {
		$transformer = $this->discoveredTable__transformers['caption'] ?? null;

		return $transformer?->transform( $node, $this ) ?? trim( $node->textContent );
	}

	/** @return array{0:?DOMElement,1:?list<string>} */
	private function headStructureContentFrom( DOMElement $node ): array {
		$headIterator = $this->getChildNodesIteratorFrom( $node );
		$row          = null;

		while ( ! $row && $headIterator->valid() ) {
			$this->isTableRowStructure( $node = $headIterator->current() ) && ( $row = $node );

			$headIterator->next();
		}

		return $row ? array( $row, $this->inferTableHeadFrom( $row ) ) : array( null, null );
	}

	/** @return ?array{0:DOMElement,1:?DOMElement,2:?DOMElement} */
	private function traceStructureFrom( DOMNode $node ): ?array {
		if ( ! $tableIterator = $this->fromCurrentStructure( $node ) ) {
			return null;
		}

		$bodyNode = $captionNode = $headNode = null;

		while ( ! $bodyNode && $tableIterator->valid() ) {
			$captionNode = $this->toNextStructureIfInCurrentPosition( Table::Caption, $tableIterator );
			$headNode    = $this->toNextStructureIfInCurrentPosition( Table::THead, $tableIterator );
			$bodyNode    = $this->toNextStructureIfInCurrentPosition( Table::TBody, $tableIterator );

			$tableIterator->next();
		}

		return $bodyNode ? array( $bodyNode, $captionNode, $headNode ) : null;
	}

	/** @return ?list<string> */
	private function dispatchEventListenerForDiscoveredTableHead( ?DOMElement $node ): ?array {
		if ( ! $node ) {
			return null;
		}

		[$row, $headContents] = $this->headStructureContentFrom( $node );

		if ( ! $row || ! $headContents ) {
			return null;
		}

		$this->registerCurrentTableHead( $headContents );
		$this->fireEventListenerDispatchedFor( Table::THead, EventAt::End, $node );

		return $headContents;
	}

	private function continueAfterFiringEventListenerWhenHeadFoundInBody( Iterator $iterator ): bool {
		if ( ! $node = AssertDOMElement::nextIn( $iterator, Table::Row ) ) {
			return false;
		}

		$this->fireEventListenerDispatchedFor( Table::THead, EventAt::End, $node );

		$iterator->next();

		return true;
	}

	/**
	 * @param DOMElement $body
	 * @return Iterator<array-key,ArrayObject<array-key,TColumnReturn>>
	 */
	private function bodyStructureIteratorFrom( DOMElement $body ): Iterator {
		[$headInspected, $position, $transformer] = $this->useCurrentTableBodyDetails();
		$iterator                                 = $this->getChildNodesIteratorFrom( $body );
		$bodyStarted                              = false;

		while ( $iterator->valid() ) {
			if ( ! $node = AssertDOMElement::nextIn( $iterator, Table::Row ) ) {
				return;
			}

			$isHead        = ! $headInspected && $this->inspectFirstRowForHeadStructure( $node );
			$headInspected = true;

			// Contents of <tr> as head MUST NOT BE COLLECTED as table column also.
			// Advance table body to next <tr> if first row is collected as head.
			if ( $isHead ) {
				if ( ! $this->continueAfterFiringEventListenerWhenHeadFoundInBody( $iterator ) ) {
					return;
				}

				continue;
			}

			if ( ! $node = AssertDOMElement::nextIn( $iterator, Table::Row ) ) {
				return;
			}

			if ( ! $bodyStarted ) {
				$this->fireEventListenerDispatchedFor( Table::Row, EventAt::Start, $body );

				$bodyStarted = true;
			}

			// TODO: add support whether to skip yielding empty <tr> or not.
			if ( ! trim( $node->textContent ) ) {
				$iterator->next();

				continue;
			}

			$content = $transformer?->transform( $node, $this ) ?? $node->childNodes;

			match ( true ) {
				$content instanceof CollectionSet => yield $content->key => $content->value,
				$content instanceof ArrayObject   => yield $content,
				default                           => yield new ArrayObject( $this->inferTableDataFrom( $content ) ),
			};

			$this->registerCurrentIterationTableRow( ++$position );

			$iterator->next();
		}//end while

		$this->fireEventListenerDispatchedFor( Table::Row, EventAt::End, $body );
	}

	private function inspectFirstRowForHeadStructure( DOMNode $row ): bool {
		( $firstRowContent = $this->inferTableHeadFrom( $row ) )
			&& $this->currentIteration__allTableHeads
			&& $this->registerCurrentTableHead( $firstRowContent );

		return $this->currentIteration__allTableHeads;
	}

	private function discoveredTargetedTable( mixed $node ): bool {
		return ! $this->shouldPerform__allTableDiscovery
			&& AssertDOMElement::isValid( $node )
			&& $this->isTargetedTable( $node );
	}
}
