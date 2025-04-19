<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits\Table;

use DOMNode;
use Iterator;
use ArrayObject;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Enums\EventAt;
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

		$this->dispatchEventListenerForTable( $id = $this->get64bitHash( $table ), $table );

		$this->discoveredTable__captions[ $id ] = $traceCaption
			? $this->captionStructureContentFrom( $table )
			: null;

		$traceHead && $this->contentsAfterFiringEventListenerWhenHeadFound( $table );

		$iterator = $this->bodyStructureIteratorFrom( $body );

		$iterator->valid() && ( $this->discoveredTable__rows[ $id ] = $iterator );
	}

	/**
	 * Accepts either internally extracted row as array, or from transformer as DOMNode (or string expected).
	 *
	 * @param iterable<array-key,DOMNode|array{0:string,1:string,2:string,3:string,4:string}> $elementList
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

			if ( false !== ( $offset[ $currentPosition ] ?? false ) ) {
				continue;
			}

			if ( $this->hasColumnReachedAtLastPosition( $currentPosition, $lastPosition ) ) {
				break;
			}

			$this->registerCurrentIterationTableColumn( $keys[ $currentPosition ] ?? null, $currentPosition + 1 );

			// Value of $column depends on row transformer return. Default is normalized array.
			// Nested table structure discovery is not supported.
			$this->registerCurrentTableColumn( $column, $transformer, $data );

			unset( $this->currentIteration__columnName );
		}//end foreach

		return $data;
	}

	/**
	 * @param iterable<array-key,array{0:string,1:string,2:string,3:string,4:string}> $elementList
	 * @return ?list<string>
	 */
	protected function inferTableHeadFrom( iterable $elementList, string $node ): ?array {
		$this->fireEventListenerOf( Table::THead, EventAt::Start, $node );

		[$names, $skippedNodes, $transformer] = $this->useCurrentTableHeadDetails();

		foreach ( $elementList as $currentIndex => $head ) {
			[$node, $nodeName, $attribute, $content] = $head;

			if ( Table::Head->value !== $nodeName ) {
				$this->tickCurrentHeadIterationSkippedHeadNode();

				++$skippedNodes;

				continue;
			}

			$position = $currentIndex - $skippedNodes;

			$this->registerCurrentIterationTableHead( $position );

			$names[] = $transformer?->transform( $head, $this ) ?? trim( $content );
		}

		return $names ?: null;
	}

	protected function captionStructureContentFrom( string $content ): ?string {
		$matched     = preg_match( '/<caption(.*?)>(.*?)<\/caption>/', subject: $content, matches: $caption );
		$transformer = $this->discoveredTable__transformers['caption'] ?? null;

		return $matched && ! empty( $caption[2] ) ? $transformer?->transform( $caption, $this ) : null;
	}

		/** @return array{0:?string,1:?list<string>} */
	protected function headStructureContentFrom( string $string ): array {
		$matched   = preg_match( '/<thead(.*?)>(.*?)<\/thead>/', subject: $string, matches: $thead );
		$unmatched = array( null, null );

		if ( ! $matched || empty( $thead[2] ) ) {
			return $unmatched;
		}

		[$rowsFound, $tableRows] = Normalize::tableRowsFrom( $thead[2] );

		if ( ! $rowsFound || empty( $tableRows ) || ! $firstRow = reset( $tableRows ) ) {
			return $unmatched;
		}

		[$columnsFound, $tableColumns] = Normalize::tableColumnsFrom( $firstRow[2] );

		return $columnsFound
			? array( $firstRow[0], $this->inferTableHeadFrom( $tableColumns, $firstRow[0] ) )
			: array( null, null );
	}

	/** @return ?array{0:string,1:list<array{0:string,1:string,2:string}>} */
	protected function bodyStructureContentFrom( string $node ): ?array {
		// NOTE: Does not support nested table.
		$matched = preg_match( '/<tbody(.*?)>(.*?)<\/tbody>/', subject: $node, matches: $tbody );

		if ( ! $matched || ! isset( $tbody[2] ) ) {
			return null;
		}

		[$rowFound, $tableRows] = Normalize::tableRowsFrom( $tbody[2] );

		if ( ! $rowFound || empty( $tableRows ) ) {
			return null;
		}

		return array( $tbody[0], $tableRows );
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
	 *   1 : array{0:string,1:list<array{0:string,1:string,2:string}>},
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

	/** @return ?list<string> */
	private function contentsAfterFiringEventListenerWhenHeadFound( string $table ): ?array {
		[$row, $headContents] = $this->headStructureContentFrom( $table );

		if ( ! $row || ! $headContents ) {
			return null;
		}

		$this->registerCurrentTableHead( $headContents );
		$this->fireEventListenerOf( Table::THead, EventAt::End, $row );

		return $headContents;
	}

	/**
	 * @param array{0:string,1:list<array{0:string,1:string,2:string}>} $body
	 * @return Iterator<array-key,ArrayObject<array-key,TColumnReturn>>
	 */
	private function bodyStructureIteratorFrom( array $body ): Iterator {
		[$headInspected, $position, $transformer] = $this->useCurrentTableBodyDetails();
		$bodyStarted                              = false;
		[$tbodyNode, $rows]                       = $body;

		while ( false !== ( $row = current( $rows ) ) ) {
			[$node, $attribute, $content] = $row;
			[$columnsFound, $columns]     = Normalize::tableColumnsFrom( $content );

			// ‼️No columns found‼️ Should never have happened in the first place. I mean,
			// why would there be no table columns in the middle of the table row 🤔?
			if ( ! $columnsFound || empty( $columns ) ) {
				next( $rows );

				continue;
			}

			$isHead        = ! $headInspected && $this->inspectFirstRowForHeadStructure( $columns, $node );
			$headInspected = true;

			// Contents of <tr> as head MUST NOT BE COLLECTED as table column also.
			// Advance table body to next <tr> if first row is collected as head.
			if ( $isHead ) {
				$this->fireEventListenerOf( Table::THead, EventAt::End, $node );

				next( $rows );

				continue;
			}

			if ( ! $bodyStarted ) {
				$this->fireEventListenerOf( Table::Row, EventAt::Start, $tbodyNode );

				$bodyStarted = true;
			}

			$content = $transformer?->transform( $columns, $this ) ?? $columns;

			match ( true ) {
				$content instanceof CollectionSet => yield $content->key => $content->value,
				$content instanceof ArrayObject   => yield $content,
				default                           => yield new ArrayObject( $this->inferTableDataFrom( $content ) ),
			};

			$this->registerCurrentIterationTableRow( ++$position );

			next( $rows );
		}//end while

		$this->fireEventListenerOf( Table::Row, EventAt::End, $tbodyNode );
	}

	/** @param array{0:string,1:string,2:string,3:string,4:string}[] $row */
	private function inspectFirstRowForHeadStructure( array $row, string $node ): bool {
		( $firstRowContent = $this->inferTableHeadFrom( $row, $node ) )
			&& $this->currentIteration__allTableHeads
			&& $this->registerCurrentTableHead( $firstRowContent );

		return $this->currentIteration__allTableHeads;
	}
}
