<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits\Table;

use DOMNode;
use Iterator;
use DOMElement;
use ArrayObject;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Enums\EventAt;
use TheWebSolver\Codegarage\Scraper\Helper\Normalize;
use TheWebSolver\Codegarage\Scraper\Event\TableTraced;
use TheWebSolver\Codegarage\Scraper\Data\CollectionSet;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Traits\Table\TableExtractor;

/** @template TColumnReturn */
trait HtmlTableFromString {
	/** @use TableExtractor<TColumnReturn> */
	use TableExtractor {
		TableExtractor::withAllTables as private extractWithAllTables;
	}

	/** @throws ScraperError When this method is used for multiple table discovery. */
	final public function withAllTables( bool $trace = false ): static {
		$trace && throw ScraperError::trigger(
			'%s trait does not support discovering multiple tables.',
			HtmlTableFromString::class
		);

		return $this;
	}

	/** @throws InvalidSource When given $source is not a string. */
	public function inferTableFrom( string|DOMElement $source, bool $normalize = true ): void {
		$this->validateSourceHasTableStructure( $source );

		$node = $normalize ? Normalize::controlsAndWhitespacesIn( $source ) : $source;

		if ( ! $tableStructure = $this->traceStructureFrom( $node ) ) {
			return;
		}

		[[$table], $body, $traceCaption, $traceHead] = $tableStructure;

		$this->dispatchEventForTable( $id = $this->get64bitHash( $table ), $table );

		$traceCaption && $this->captionStructureContentFrom( $table );
		$traceHead && $this->headStructureContentFrom( $table );

		$iterator = $this->bodyStructureIteratorFrom( $body );

		$iterator->valid() && ( $this->discoveredTable__rows[ $id ] = $iterator );

		$this->dispatchEvent( new TableTraced( Table::TBody, EventAt::End, $table, $this ) );
	}

	/** @param iterable<array-key,array{0:string,1:string,2:string,3:string,4:string}> $elementList */
	public function inferTableHeadFrom( iterable $elementList ): void {
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

		$this->registerCurrentTableHead( $names );
	}

	/** @param iterable<array-key,DOMNode|array{0:string,1:string,2:string,3:string,4:string}> $elementList */
	public function inferTableDataFrom( iterable $elementList ): array {
		$data = [];

		[$keys, $lastPosition, $skippedNodes, $transformer] = $this->useCurrentTableColumnDetails();

		foreach ( $elementList as $currentIndex => $column ) {
			if ( ! $this->isTableColumnStructure( $column ) ) {
				++$skippedNodes;

				continue;
			}

			$currentPosition = $currentIndex - $skippedNodes;

			if ( $this->shouldSkipTableColumnIn( $currentPosition ) ) {
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

	private function captionStructureContentFrom( string $table ): void {
		[$matched, $caption] = Normalize::nodeToMatchedArray( $table, Table::Caption );

		if ( ! $matched ) {
			return;
		}

		$this->dispatchEvent( new TableTraced( Table::Caption, EventAt::Start, $caption[0], $this ) );

		$transformer = $this->discoveredTable__transformers['caption'] ?? null;
		$content     = $transformer?->transform( $caption, $this ) ?? trim( $caption[2] );

		$this->discoveredTable__captions[ $this->currentTable__id ] = $content;

		$this->dispatchEvent( new TableTraced( Table::Caption, EventAt::End, $caption[0], $this ) );
	}

	private function headStructureContentFrom( string $string ): void {
		[$matched, $thead] = Normalize::nodeToMatchedArray( $string, Table::THead );

		if ( ! $matched ) {
			return;
		}

		$this->dispatchEvent( $event = new TableTraced( Table::THead, EventAt::Start, $thead[0], $this ) );

		if ( $event->shouldStopTrace() ) {
			$this->dispatchEvent( new TableTraced( Table::THead, EventAt::End, $thead[0], $this ) );

			return;
		}

		[$rowsFound, $headRow] = Normalize::nodeToMatchedArray( $thead[2], Table::Row );

		if ( $rowsFound ) {
			[$headsFound, $rowColumns] = Normalize::tableColumnsFrom( $headRow[2] );

			$headsFound && $this->inferTableHeadFrom( $rowColumns );
		}

		$this->dispatchEvent( new TableTraced( Table::THead, EventAt::End, $thead[0], $this ) );
	}

	/** @return ?array{0:string,1:list<array{0:string,1:string,2:string}>} */
	private function bodyStructureContentFrom( string $node ): ?array {
		// Does not support nested table.
		[$matched, $tbody] = Normalize::nodeToMatchedArray( $node, Table::TBody );

		if ( ! $matched ) {
			return null;
		}

		[$rowFound, $tableRows] = Normalize::nodeToMatchedArray( $tbody[2], Table::Row, all: true );

		return $rowFound && $this->tableColumnsExistInBody( $tbody[0] ) ? [ $tbody[0], $tableRows ] : null;
	}

	private function get64bitHash( string $string ): string {
		return base64_encode( $string ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/** @return ?array{0:string,1:string,2:string} */
	private function fromCurrentStructure( string $node ): ?array {
		$content           = Normalize::controlsAndWhitespacesIn( $node );
		[$matched, $table] = Normalize::nodeToMatchedArray( $content, 'table' );

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

		return [ $table, $body, $traceCaption, $traceHead ];
	}

	/**
	 * @param array{0:string,1:list<array{0:string,1:string,2:string}>} $body
	 * @return Iterator<array-key,ArrayObject<array-key,TColumnReturn>>
	 */
	private function bodyStructureIteratorFrom( array $body ): Iterator {
		[$headInspected, $bodyStarted, $position, $transformer] = $this->useCurrentTableBodyDetails();
		[$tbodyNode, $rows]                                     = $body;

		foreach ( $rows as $row ) {
			[$node, $attribute, $content] = $row;
			[$columnsFound, $columns]     = Normalize::tableColumnsFrom( $content );

			// ‼️No columns found‼️ Should never have happened in the first place. I mean,
			// why would there be no table columns in the middle of the table row 🤔?
			if ( ! $columnsFound || empty( $columns ) ) {
				continue;
			}

			$isHead        = ! $headInspected && $this->inspectFirstRowForHeadStructure( $columns );
			$headInspected = true;

			// Contents of <tr> as head MUST NOT BE COLLECTED as table column also.
			// Advance table body to next <tr> if first row is collected as head.
			if ( $isHead ) {
				// We can only determine whether first row contains table heads after it is inferred.
				// We'll simply dispatch the ending event here to notify subscribers, if any.
				$this->dispatchEvent( new TableTraced( Table::THead, EventAt::End, $node, $this ) );

				continue;
			}

			if ( ! $bodyStarted ) {
				$this->dispatchEvent( $event = new TableTraced( Table::Row, EventAt::Start, $tbodyNode, $this ) );

				// Although not recommended, it is entirely possible to stop inferring further table rows.
				// This just means that tracer was used to trace "<th>" that were present in "<tbody>".
				if ( $event->shouldStopTrace() ) {
					break;
				}

				$bodyStarted = true;
			}

			$content = $transformer?->transform( $columns, $this ) ?? $columns;

			match ( true ) {
				$content instanceof CollectionSet => yield $content->key => $content->value,
				$content instanceof ArrayObject   => yield $content,
				default                           => yield new ArrayObject( $this->inferTableDataFrom( $content ) ),
			};

			$this->registerCurrentIterationTableRow( ++$position );
		}//end foreach

		$this->dispatchEvent( new TableTraced( Table::Row, EventAt::End, $tbodyNode, $this ) );
	}

	/** @param array{0:string,1:string,2:string,3:string,4:string}[] $row */
	private function inspectFirstRowForHeadStructure( array $row ): bool {
		$this->inferTableHeadFrom( $row );

		return $this->currentIteration__allTableHeads;
	}

	/**
	 * @throws InvalidSource When source invalid.
	 * @phpstan-assert string $source
	 */
	private function validateSourceHasTableStructure( string|DOMElement $source ): void {
		$source instanceof DOMElement && throw new InvalidSource(
			sprintf( '%s trait only supports "string" source to infer table.', HtmlTableFromString::class )
		);

		( str_contains( $source, '<table' ) && str_contains( $source, '</table>' ) ) || throw new InvalidSource(
			sprintf(
				'%s trait cannot trace table structure from string that does not have table.',
				HtmlTableFromString::class
			)
		);
	}
}
