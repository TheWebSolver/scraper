<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits\Table;

use DOMNode;
use Iterator;
use DOMElement;
use ArrayObject;
use DOMNodeList;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
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

			if ( false !== ( $offset[ $currentPosition ] ?? false ) ) {
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

			$head     = $headNode ? $this->headStructureContentFrom( $headNode ) : null;
			$iterator = $this->bodyStructureIteratorFrom( $head, $bodyNode );

			$iterator->valid() && ( $this->discoveredTable__rows[ $id ] = $iterator );

			if ( $this->discoveredTargetedTable( $node ) ) {
				break;
			}
		}//end foreach
	}

	/**
	 * Infers table head from given element list.
	 *
	 * @param DOMNodeList<DOMNode> $elementList
	 * @return ?list<string>
	 * @throws InvalidSource When element list is not DOMNodeList.
	 */
	protected function inferTableHeadFrom( DOMNodeList $elementList ): ?array {
		$thTransformer = $this->discoveredTable__transformers['th'] ?? null;
		$names         = array();
		$skippedNodes  = 0;

		foreach ( $elementList as $currentIndex => $node ) {
			if ( ! AssertDOMElement::isValid( $node, Table::Head ) ) {
				$this->tickCurrentHeadIterationSkippedHeadNode( $node );

				++$skippedNodes;

				continue;
			}

			$position = $currentIndex - $skippedNodes;

			$this->registerCurrentIterationTableHead( $position );

			$names[] = $thTransformer?->transform( $node, $this ) ?? trim( $node->textContent );
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

	/** @return ?list<string> */
	private function headStructureContentFrom( DOMElement $node ): ?array {
		$headIterator = $this->getChildNodesIteratorFrom( $node );
		$row          = null;

		while ( ! $row && $headIterator->valid() ) {
			$this->isTableRowStructure( $node = $headIterator->current() ) && ( $row = $node );

			$headIterator->next();
		}

		return $row ? $this->inferTableHeadFrom( $row->childNodes ) : null;
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

	/**
	 * @param ?list<string> $head
	 * @param DOMElement    $body
	 * @return Iterator<array-key,ArrayObject<array-key,TColumnReturn>>
	 */
	private function bodyStructureIteratorFrom( ?array $head, DOMElement $body ): Iterator {
		$iterator      = $this->getChildNodesIteratorFrom( $body );
		$transformer   = $this->discoveredTable__transformers['tr'] ?? null;
		$headInspected = false;
		$position      = $this->currentIteration__rowCount[ $this->currentTable__id ] = 0;

		while ( $iterator->valid() ) {
			if ( ! $node = AssertDOMElement::nextIn( $iterator, Table::Row ) ) {
				return;
			}

			$isHead        = ! $headInspected && $this->inspectFirstRowForHeadStructure( $head, $node );
			$headInspected = true;

			// Contents of <tr> as head MUST NOT BE COLLECTED as table column also.
			// Advance table body to next <tr> if first row is collected as head.
			if ( $isHead ) {
				$iterator->next();

				continue;
			}

			if ( ! $node = AssertDOMElement::nextIn( $iterator, Table::Row ) ) {
				return;
			}

			// TODO: add support whether to skip yielding empty <tr> or not.
			if ( ! trim( $node->textContent ) ) {
				$iterator->next();

				continue;
			}

			$head && ! $this->getColumnNames() && $this->setColumnNames( $head, $this->getTableId( true ) );

			$content = $transformer?->transform( $node, $this ) ?? $node->childNodes;

			match ( true ) {
				$content instanceof CollectionSet => yield $content->key => $content->value,
				$content instanceof ArrayObject   => yield $content,
				default                           => yield new ArrayObject( $this->inferTableDataFrom( $content ) ),
			};

			$this->registerCurrentIterationTableRow( ++$position );

			$iterator->next();
		}//end while
	}

	/** @param ?list<string> $head */
	private function inspectFirstRowForHeadStructure( ?array &$head, DOMNode $row ): bool {
		$firstRowContent = $this->inferTableHeadFrom( $row->childNodes );
		$head          ??= $this->currentIteration__allTableHeads ? $firstRowContent : null;

		$head && $this->registerCurrentTableHead( $head );

		return $this->currentIteration__allTableHeads;
	}

	private function discoveredTargetedTable( mixed $node ): bool {
		return ! $this->shouldPerform__allTableDiscovery
			&& AssertDOMElement::isValid( $node )
			&& $this->isTargetedTable( $node );
	}
}
