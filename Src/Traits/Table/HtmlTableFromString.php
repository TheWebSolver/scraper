<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits\Table;

use Iterator;
use DOMElement;
use ArrayObject;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Enums\EventAt;
use TheWebSolver\Codegarage\Scraper\Data\TableCell;
use TheWebSolver\Codegarage\Scraper\Data\TableHead;
use TheWebSolver\Codegarage\Scraper\Helper\Normalize;
use TheWebSolver\Codegarage\Scraper\Event\TableTraced;
use TheWebSolver\Codegarage\Scraper\Data\CollectionSet;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
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

	protected function useCurrentIterationValidatedHead( mixed $node ): TableHead {
		if ( is_array( $node ) ) {
			$isValid = $isAllowed = Table::Head->value === ( $node[1] ?? false );
			$value   = is_string( $value = $node[3] ?? null ) ? ( trim( $value ) ?: null ) : null;
		}

		return new TableHead( $isValid ?? false, $isAllowed ?? false, $value ?? null );
	}

	/** @param Transformer<static,string> $transformer */
	protected function transformCurrentIterationTableHead( mixed $node, Transformer $transformer ): string {
		return $transformer->transform( $this->assertThingIsValidNode( $node ), $this );
	}

	protected function getTagnameFrom( mixed $thing ): mixed {
		return match ( true ) {
			is_array( $thing )  => $thing[1] ?? null,
			is_string( $thing ) => $thing,
			default             => null
		};
	}

	/**
	 * @param Transformer<static,TColumnReturn> $transformer
	 * @return TableCell<TColumnReturn>
	 */
	protected function transformCurrentIterationTableColumn(
		mixed $node,
		Transformer $transformer,
		int $position
	): TableCell {
		return new TableCell(
			position: $position,
			value: $transformer->transform( $column = $this->assertThingIsValidNode( $node ), $this ),
			rowSpan: is_string( $count = $column[2] ?? null ) ? (int) $this->extractRowSpanFromColumn( $count ) : 0
		);
	}

	/**
	 * Nested table structure discovery inside column is not supported when this trait is used.
	 *
	 * @param ?TColumnReturn $value
	 */
	protected function afterCurrentTableColumnRegistered( mixed $column, mixed $value ): void {}

	private function captionStructureContentFrom( string $table ): void {
		[$matched, $caption] = Normalize::nodeToMatchedArray( $table, Table::Caption );

		if ( ! $matched ) {
			return;
		}

		$this->dispatchEvent( new TableTraced( Table::Caption, EventAt::Start, $caption[0], $this ) );

		$transformer = $this->discoveredTable__transformers[ Table::Caption->value ] ?? null;
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

	private function isValidStructureIfTraceable( Table $structure, string $node ): bool {
		return $this->shouldTraceTableStructure( $structure )
			&& str_contains( $node, "<{$structure->value}" )
			&& str_contains( $node, "</{$structure->value}>" );
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
		[$headInspected, $bodyStarted, $position] = $this->useCurrentTableBodyDetails();
		[$tbodyNode, $rows]                       = $body;

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

			if ( $isHead ) {
				// We can only determine whether first row contains table heads after it is inferred.
				// We'll simply dispatch the ending event here to notify subscribers, if any.
				$this->dispatchEvent( new TableTraced( Table::THead, EventAt::End, $node, $this ) );

				// Contents of <tr> as head MUST NOT BE COLLECTED as table column also.
				// Advance table body to next <tr> if first row is collected as head.
				continue;
			}

			if ( ! $bodyStarted ) {
				$this->hydrateIndicesSourceFromAttribute();
				$this->dispatchEvent( $event = new TableTraced( Table::Row, EventAt::Start, $tbodyNode, $this ) );

				// Although not recommended, it is entirely possible to stop inferring further table rows.
				// This just means that tracer was used to trace "<th>" that were present in "<tbody>".
				if ( $event->shouldStopTrace() ) {
					break;
				}

				$bodyStarted = true;
			}

			$transformer = $this->discoveredTable__transformers[ Table::Row->value ] ?? null;
			$content     = $transformer?->transform( $columns, $this ) ?? $columns;

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

		$this->dispatchEvent( new TableTraced( Table::Row, EventAt::End, $tbodyNode, $this ) );
	}

	private function extractRowSpanFromColumn( string $node ): int {
		if ( ! str_contains( $node, 'rowspan' ) ) {
			return 0;
		}

		$attributes = explode( '=', $node );
		$position   = array_search( 'rowspan', $attributes, true );

		return isset( $attributes[ $position + 1 ] )
			? (int) preg_replace( '/[^0-9]/', '', $attributes[ $position + 1 ] )
			: 0;
	}

	/** @param array{0:string,1:string,2:string,3:string,4:string}[] $row */
	private function inspectFirstRowForHeadStructure( array $row ): bool {
		$this->inferTableHeadFrom( $row );

		return $this->currentIteration__allTableHeads;
	}

	private function tableColumnsExistInBody( string $body ): bool {
		return str_contains( $body, '<td' ) && str_contains( $body, '</td>' );
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

	/**
	 * @return string|non-empty-list<mixed>
	 * @phpstan-assert string|non-empty-list<mixed> $node
	 */
	private function assertThingIsValidNode( mixed $node ): string|array {
		is_string( $node )
			|| ( is_array( $node ) && array_is_list( $node ) && ! empty( $node ) )
			|| $this->throwUnsupportedNodeType( $node, HtmlTableFromString::class );

		return $node;
	}
}
