<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits\Table;

use DOMNode;
use Iterator;
use ArrayObject;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Helper\Normalize;
use TheWebSolver\Codegarage\Scraper\Data\CollectionSet;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Traits\Table\TableExtractor;

/** @template TColumnReturn */
trait HtmlTableFromString {
	/** @use TableExtractor<TColumnReturn> */
	use TableExtractor {
		TableExtractor::withAllTables as protected extractWithAllTables;
	}

	/** @throws ScraperError When this method is used for multiple table discovery. */
	final public function withAllTables( bool $trace = false ): static {
		$trace && throw ScraperError::trigger(
			'%s trait does not support discovering multiple tables.',
			HtmlTableFromString::class
		);

		return $this;
	}

	public function inferTableFrom( string $source, bool $normalize = true ): void {
		$node = $normalize ? Normalize::controlsAndWhitespacesIn( $source ) : $source;

		if ( ! $tableStructure = $this->traceStructureFrom( $node ) ) {
			return;
		}

		[[$table], $body, $traceCaption, $traceHead] = $tableStructure;

		$this->dispatchEventListenerForDiscoveredTable( $id = $this->get64bitHash( $table ), $table );

		$this->discoveredTable__captions[ $id ] = $traceCaption
			? $this->captionStructureContentFrom( $table )
			: null;

		$head     = $traceHead ? $this->headStructureContentFrom( $table ) : null;
		$iterator = $this->bodyStructureIteratorFrom( $head, $body );

		$iterator->valid() && ( $this->discoveredTable__rows[ $id ] = $iterator );
	}

	/**
	 * Accepts either internally extracted row as array, or from transformer as DOMNode (or string expected).
	 *
	 * @param iterable<array-key,string|DOMNode|array{0:string,1:string,2:string,3:string}> $elementList
	 */
	public function inferTableDataFrom( iterable $elementList ): array {
		$data = array();

		[$keys, $offset, $lastPosition, $skippedNodes, $transformer] = $this->useCurrentTableColumnDetails();

		foreach ( $elementList as $currentIndex => $column ) {
			if ( ! $this->isTableColumnStructure( $column ) ) {
				++$skippedNodes;

				continue;
			}

			$currentPosition = $currentIndex - $skippedNodes;
			$content         = $column instanceof DOMNode
				? $column->textContent
				: ( is_string( $column ) ? $column : $column[3] ); // REVIEW: Transformer may never return string.

			if ( false !== ( $offset[ $currentPosition ] ?? false ) ) {
				continue;
			}

			if ( $this->hasColumnReachedAtLastPosition( $currentPosition, $lastPosition ) ) {
				break;
			}

			$this->registerCurrentIterationTableColumn( $keys[ $currentPosition ] ?? null, $currentPosition + 1 );

			// Nested table structure discovery is not supported.
			$this->registerCurrentTableColumn( $content, $transformer, $data );

			unset( $this->currentIteration__columnName );
		}//end foreach

		return $data;
	}

	/**
	 * @param iterable<array-key,array{0:string,1:string,2:string,3:string}> $elementList
	 * @return ?list<string>
	 */
	protected function inferTableHeadFrom( iterable $elementList ): ?array {
		$thTransformer = $this->discoveredTable__transformers['th'] ?? null;
		$names         = array();
		$skippedNodes  = 0;

		foreach ( $elementList as $currentIndex => [$node, $nodeName, $attribute, $content] ) {
			if ( Table::Head->value !== $nodeName ) {
				$this->tickCurrentHeadIterationSkippedHeadNode();

				++$skippedNodes;

				continue;
			}

			$position = $currentIndex - $skippedNodes;

			$this->registerCurrentIterationTableHead( $position );

			$names[] = $thTransformer?->transform( $node, $position, $this ) ?? trim( $content );
		}

		return $names ?: null;
	}

	protected function captionStructureContentFrom( string $content ): ?string {
		$matched     = preg_match( '/<caption(.*?)>(.*?)<\/caption>/', subject: $content, matches: $caption );
		$transformer = $this->discoveredTable__transformers['caption'] ?? null;

		return $matched && ! empty( $caption[2] ) ? $transformer?->transform( $caption[0], 0, $this ) : null;
	}

	/** @return ?list<string> */
	protected function headStructureContentFrom( string $string ): ?array {
		$matched = preg_match( '/<thead(.*?)>(.*?)<\/thead>/', subject: $string, matches: $thead );

		if ( ! $matched || empty( $thead[2] ) ) {
			return null;
		}

		[$rowsFound, $tableRows] = $this->extractTableRowsFrom( $thead[2] );

		if ( ! $rowsFound || empty( $tableRows ) || ! $firstRow = reset( $tableRows ) ) {
			return null;
		}

		[$columnsFound, $tableColumns] = $this->extractTableColumnFrom( $firstRow[2] );

		return $columnsFound ? $this->inferTableHeadFrom( $tableColumns ) : null;
	}

	/** @return ?list<array{0:string,1:string,2:string}> */
	protected function bodyStructureContentFrom( string $node ): ?array {
		// TODO: use negative look-head to ignore nested tables.
		$matched = preg_match( '/<tbody(.*?)>(.*?)<\/tbody>/', subject: $node, matches: $tbody );

		if ( ! $matched || ! isset( $tbody[2] ) ) {
			return null;
		}

		[$rowFound, $tableRows] = $this->extractTableRowsFrom( $tbody[2] );

		if ( ! $rowFound || empty( $tableRows ) ) {
			return null;
		}

		return $tableRows;
	}

	private function get64bitHash( string $string ): string {
		return base64_encode( $string ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/** @return ?array{0:string,1:string,2:string} */
	private function fromCurrentStructure( string $node ): ?array {
		$content = Normalize::controlsAndWhitespacesIn( $node );
		$matched = preg_match( '/<table(.*?)>(.*?)<\/table>/', subject: $content, matches: $table );

		return $matched && ! empty( $table ) ? $table : null;
	}

	private function isValidStructureIfTraceable( Table $target, string $node ): bool {
		return $this->shouldTraceTableStructure( $target )
			&& str_contains( $node, "<{$target->value}" )
			&& str_contains( $node, "</{$target->value}>" );
	}

	/**
	 * @return ?array{
	 *   0 : array{0:string,1:string,2:string},
	 *   1 : list<array{0:string,1:string,2:string}>,
	 *   2 : bool,
	 *   3 : bool
	 * }
	 */
	private function traceStructureFrom( string $node ): ?array {
		if ( ! $table = $this->fromCurrentStructure( $node ) ) {
			return null;
		}

		if ( ! $body = $this->bodyStructureContentFrom( $table[0] ) ) {
			return null;
		}

		$traceCaption = $this->isValidStructureIfTraceable( Table::Caption, $table[0] );
		$traceHead    = $this->isValidStructureIfTraceable( Table::THead, $table[0] );

		return array( $table, $body, $traceCaption, $traceHead );
	}

	/** @return array{0:int|false,1:list<array{0:string,1:string,2:string}>} */
	private function extractTableRowsFrom( string $string ): array {
		$matched = preg_match_all(
			pattern: '/<tr(.*?)>(.*?)<\/tr>/',
			subject: $string,
			matches: $tableRows,
			flags: PREG_SET_ORDER
		);

		return array( $matched, $tableRows );
	}

	/** @return array{0:int|false,1:list<array{0:string,1:string,2:string,3:string,4:string}>} */
	private function extractTableColumnFrom( string $string ): array {
		$matched = preg_match_all(
			pattern: '/<(th|td)(.*?)>(.*?)<\/(th|td)>/',
			subject: $string,
			matches: $tableColumns,
			flags: PREG_SET_ORDER
		);

		return array( $matched, $tableColumns );
	}

	/**
	 * @param ?list<string>                           $head
	 * @param list<array{0:string,1:string,2:string}> $body
	 * @return Iterator<array-key,ArrayObject<array-key,TColumnReturn>>
	 */
	private function bodyStructureIteratorFrom( ?array $head, array $body ): Iterator {
		$rowTransformer = $this->discoveredTable__transformers['tr'] ?? null;
		$headInspected  = false;
		$position       = 0;
		$iterator       = ( new ArrayObject( $body ) )->getIterator();

		while ( $iterator->valid() ) {
			[$node, $attribute, $content]  = $iterator->current();
			[$columnsFound, $tableColumns] = $this->extractTableColumnFrom( $content );

			if ( ! $columnsFound || empty( $tableColumns ) ) {
				$iterator->next();

				continue;
			}

			$isHead        = ! $headInspected && $this->inspectFirstRowForHeadStructure( $head, $iterator, $tableColumns );
			$headInspected = true;

			if ( $isHead ) {
				continue;
			}

			$head && ! $this->getColumnNames() && $this->setColumnNames( $head, $this->getTableId( true ) );

			$content = $rowTransformer?->transform( $node, $position, $this ) ?? $tableColumns;

			match ( true ) {
				$content instanceof CollectionSet => yield $content->key => $content->value,
				$content instanceof ArrayObject   => yield $content,
				default                           => yield new ArrayObject( $this->inferTableDataFrom( $content ) ),
			};

			++$position;

			$iterator->next();
		}//end while
	}

	/**
	 * @param ?list<string>                                $head
	 * @param array{0:string,1:string,2:string,3:string}[] $row
	 */
	private function inspectFirstRowForHeadStructure( ?array &$head, Iterator $iterator, array $row ): bool {
		$firstRowContent = $this->inferTableHeadFrom( $row );
		$head          ??= $this->currentIteration__allTableHeads ? $firstRowContent : null;

		// Contents of <tr> as head MUST NOT BE COLLECTED as a Table Data also.
		// Advance iterator to next <tr> if first row is collected as head.
		( $isHead = $this->currentIteration__allTableHeads ) && $iterator->next();

		$head && $this->registerCurrentTableHead( $head );

		return $isHead;
	}
}
