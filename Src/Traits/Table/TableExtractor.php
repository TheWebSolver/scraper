<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits\Table;

use Closure;
use DOMNode;
use Iterator;
use BackedEnum;
use DOMElement;
use ArrayObject;
use SplFixedArray;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Helper\Normalize;
use TheWebSolver\Codegarage\Scraper\Data\CollectionSet;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Marshaller\MarshallItem;

/** @template TColumnReturn */
trait TableExtractor {
	/** @placeholder `1:` static classname, `2:` throwing methodname, `3:` Table enum, `4:` Table case, `5:` reason. */
	final public const USE_EVENT_LISTENER = 'Invalid invocation of "%1$s::%2$s()". Use event listener for "%3$s::%4$s" to %5$s';

	private bool $shouldPerform__allTableDiscovery = false;

	/**
	 * @var array{
	 *   tr      ?: Transformer<static, CollectionSet<TColumnReturn>|iterable<int,string|DOMNode>>,
	 *   caption ?: Transformer<static, string>,
	 *   th      ?: Transformer<static, string>,
	 *   td      ?: Transformer<static, TColumnReturn>,
	 * }
	 */
	private array $discoveredTable__transformers;
	/**
	 * @var array<string,array{
	 *   0 ?: Closure( static, string|DOMElement ): mixed,
	 *   1 ?: Closure( static, string|DOMElement ): mixed
	 * }>
	 */
	private array $discoveredTable__eventListeners;
	/** @var array<string,array{start:bool,finish:bool}> */
	private array $discoveredTable__eventListenersFired = array();
	/** @var array{0:Table,1:bool} */
	private array $discoveredTable__eventListenerFiring;

	/** @var (int|string)[] */
	private array $discoveredTable__ids = array();

	/** @var list<Table> */
	private array $discoveredTable__excludedStructures = array();
	/** @var (string|null)[] */
	private array $discoveredTable__captions = array();
	/** @var SplFixedArray<string>[] */
	private array $discoveredTable__headNames = array();
	/** @var Iterator<array-key,ArrayObject<array-key,TColumnReturn>>[] */
	private array $discoveredTable__rows = array();

	private int|string $currentTable__id;
	/** @var array{0:array<int,string>,1:array<int,int>,2:int}[] Names, offsets, & last index */
	private array $currentTable__columnInfo;

	/** @var int[] */
	private array $currentIteration__rowCount = array();
	/** @var int[] */
	private array $currentIteration__columnCount  = array();
	private bool $currentIteration__allTableHeads = true;
	private string $currentIteration__columnName;
	private int $currentIteration__headInfo;

	public function withAllTables( bool $trace = true ): static {
		$this->shouldPerform__allTableDiscovery = $trace;

		return $this;
	}

	public function traceWithout( Table ...$targets ): static {
		$this->discoveredTable__excludedStructures = $targets;

		return $this;
	}

	public function addTransformer( Table $for, Transformer $transformer ): static {
		$this->discoveredTable__transformers[ $for->value ] = $transformer;

		return $this;
	}

	public function addEventListener( Table $for, callable $callback, bool $finish = false ): static {
		$this->discoveredTable__eventListeners[ $for->value ][ $finish ? 1 : 0 ] = $callback( ... );

		return $this;
	}

	public function setItemsIndices( array $keys, int ...$offset ): void {
		if ( ! $this->isFiringEventListenerFor( Table::Row, finish: false ) ) {
			$placeholders = array( static::class, __FUNCTION__, Table::class, Table::Row->name );

			throw new ScraperError(
				sprintf( self::USE_EVENT_LISTENER, ...array( ...$placeholders, 'set column names.' ) )
			);
		}

		$id                                    = $this->getTableId( true );
		$this->currentTable__columnInfo[ $id ] = Normalize::listWithOffset( $keys, $offset );
	}

	/** @return ($current is true ? int|string : (int|string)[]) */
	public function getTableId( bool $current = false ): int|string|array {
		return $current ? $this->currentTable__id ?? 0 : $this->discoveredTable__ids;
	}

	public function getTableCaption(): array {
		return $this->discoveredTable__captions;
	}

	public function getTableHead(): array {
		return $this->discoveredTable__headNames;
	}

	public function getTableData(): array {
		return $this->discoveredTable__rows;
	}

	/** @return array<int,string> */
	public function getItemsIndices(): array {
		return $this->currentTable__columnInfo[ $this->currentTable__id ][0] ?? array();
	}

	public function getCurrentItemIndex(): ?string {
		return $this->currentIteration__columnName ?? null;
	}

	public function getCurrentIterationCountOf( ?BackedEnum $type = null, bool $offsetInclusive = false ): ?int {
		if ( Table::Head === $type ) {
			return isset( $this->currentIteration__headInfo ) ? $this->currentIteration__headInfo + 1 : null;
		} elseif ( Table::Row === $type ) {
			return $this->currentIteration__rowCount[ $this->currentTable__id ] ?? null;
		} elseif ( Table::Column === $type ) {
			if ( ! isset( $this->currentTable__id ) ) {
				return null;
			}

			$count = $this->currentIteration__columnCount[ $this->currentTable__id ] ?? null;

			if ( null === $count ) {
				return null;
			}

			$column = $this->currentTable__columnInfo[ $this->currentTable__id ] ?? null;

			return ! $offsetInclusive && $column ? $count - count( $column[1] ) : $count;
		}

		return null;
	}

	/** Accepts either text content, extracted array or converted DOMElement from default Table Row Marshaller. */
	protected function isTableColumnStructure( mixed $node ): bool {
		$nodeName = match ( true ) {
			$node instanceof DOMElement => $node->tagName,
			is_array( $node )           => $node[1] ?? null,
			is_string( $node )          => $node,
			default                     => null
		};

		return ( $nodeName && ( Table::Head->value === $nodeName || Table::Column->value === $nodeName ) );
	}

	protected function dispatchEventListenerForDiscoveredTable( int|string $id, string|DOMElement $body ): void {
		if ( ! in_array( $id, $this->discoveredTable__ids, true ) ) {
			$this->discoveredTable__ids[] = $this->currentTable__id = $id;
		}

		$this->fireEventListenerRegisteredFor( Table::TBody, finish: false, node: $body );
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- To be used by exhibiting class.
	protected function isTargetedTable( string|DOMElement $node ): bool {
		return true;
	}

	public function resetTableHooks(): void {
		unset(
			$this->discoveredTable__transformers,
			$this->discoveredTable__eventListeners,
			$this->discoveredTable__eventListenerFiring,
		);

		$this->discoveredTable__eventListenersFired = array();
	}

	public function resetTableTraced(): void {
			$this->discoveredTable__excludedStructures = array();
			$this->discoveredTable__captions           = array();
			$this->discoveredTable__headNames          = array();
			$this->discoveredTable__rows               = array();
	}

	/** @return ?Closure(static, string|DOMElement): mixed */
	private function getEventListenersRegisteredFor( Table $table, bool $finish = false ): ?Closure {
		$listeners = $this->discoveredTable__eventListeners[ $table->value ] ?? null;

		return $finish ? $listeners[1] ?? null : $listeners[0] ?? null;
	}

	private function fireEventListenerRegisteredFor( Table $table, bool $finish, string|DOMElement $node ): void {
		$fireEvent      = $this->getEventListenersRegisteredFor( $table, $finish );
		$firedPositions = array(
			'start'  => false,
			'finish' => false,
		);

		if ( ! $fireEvent ) {
			$this->discoveredTable__eventListenersFired[ $table->value ] = $firedPositions;

			return;
		}

		$firedPositions[ $finish ? 'finish' : 'start' ] = true;
		$this->discoveredTable__eventListenerFiring     = array( $table, $finish );

		$fireEvent( $this, $node );

		unset( $this->discoveredTable__eventListenerFiring );

		$this->discoveredTable__eventListenersFired[ $table->value ] = $firedPositions;
	}

	private function isFiringEventListenerFor( Table $table, bool $finish ): bool {
		if ( ! isset( $this->discoveredTable__eventListenerFiring ) ) {
			return false;
		}

		[$currentTable, $firedAt] = $this->discoveredTable__eventListenerFiring;

		return $currentTable === $table && $firedAt === $finish;
	}

	private function hasFiredEventListenerFor( Table $table, bool $finish ): bool {
		$firedAt = $this->discoveredTable__eventListenersFired[ $table->value ] ?? array();

		return $finish ? $firedAt['finish'] ?? false : $firedAt['start'] ?? false;
	}

	/** @return array{0:array<int,string>,1:array<int,int>,2:?int,3:int,4:Transformer<static,TColumnReturn>} */
	private function useCurrentTableColumnDetails(): array {
		$transformer = $this->discoveredTable__transformers[ Table::Column->value ] ?? new MarshallItem();
		$columns     = $this->currentTable__columnInfo[ $this->currentTable__id ] ?? array();

		return array(
			$columnNames  = $columns[0] ?? array(),
			$offset       = $columns[1] ?? array(),
			$lastPosition = $columns[2] ?? null,
			$skippedNodes = $this->currentIteration__columnCount[ $this->currentTable__id ] = 0,
			$transformer,
		);
	}

	/**
	 * @return array{0 :  list<string>,1 :  int,2 :? Transformer<static,string>}
	 */
	private function useCurrentTableHeadDetails(): array {
		return array(
			$names         = array(),
			$skippedNodes  = 0,
			$thTransformer = $this->discoveredTable__transformers[ Table::Head->value ] ?? null,
		);
	}

	/**
	 * @return array{
	 *   0 :  bool,
	 *   1 :  int,
	 *   2 :? Transformer<static, CollectionSet<TColumnReturn>|iterable<int,string|DOMNode>>
	 * }
	 */
	private function useCurrentTableBodyDetails(): array {
		return array(
			$headInspected = false,
			$position      = $this->currentIteration__rowCount[ $this->currentTable__id ] = 0,
			$transformer   = $this->discoveredTable__transformers[ Table::Row->value ] ?? null,
		);
	}

	private function shouldTraceTableStructure( Table $target ): bool {
		return ! in_array( $target, $this->discoveredTable__excludedStructures, strict: true );
	}

	private function hasColumnReachedAtLastPosition( int $currentPosition, ?int $lastPosition ): bool {
		return null !== $lastPosition && $currentPosition > $lastPosition;
	}

	private function tickCurrentHeadIterationSkippedHeadNode( mixed $node = null ): void {
		$this->currentIteration__allTableHeads
			&& ( $this->currentIteration__allTableHeads = $node instanceof DOMNode
				&& XML_COMMENT_NODE === $node->nodeType );
	}

	/** @param list<string> $names */
	private function registerCurrentTableHead( array $names ): void {
		$tableId                                      = $this->getTableId( current: true );
		$this->discoveredTable__headNames[ $tableId ] = SplFixedArray::fromArray( $names );

		$this->registerCurrentIterationTableHead( false );
	}

	private function registerCurrentIterationTableHead( int|false $index = 0 ): void {
		if ( false === $index ) {
			unset( $this->currentIteration__headInfo );

			return;
		}

		$this->currentIteration__headInfo = $index;
	}

	private function registerCurrentIterationTableRow( int $count ): void {
		$this->currentIteration__rowCount[ $this->currentTable__id ] = $count;
	}

	/**
	 * @param string|array{0:string,1:string,2:string,3:string,4:string}|DOMElement $element
	 * @param Transformer<static,TColumnReturn>                                     $transformer
	 * @param array<array-key,TColumnReturn>                                        $data
	 * @return ?TColumnReturn
	 */
	private function registerCurrentTableColumn(
		string|array|DOMElement $element,
		Transformer $transformer,
		array &$data
	): mixed {
		$count    = $this->getCurrentIterationCountOf( Table::Column );
		$position = $count ? $count - 1 : 0;
		$value    = $transformer->transform( $element, $this );

		return ( ! is_null( $value ) && '' !== $value )
			? ( $data[ $this->getCurrentItemIndex() ?? $position ] = $value )
			: null;
	}

	private function registerCurrentIterationTableColumn( ?string $name, int $count ): void {
		$this->currentIteration__columnCount[ $this->currentTable__id ] = $count;
		$name && $this->currentIteration__columnName                    = $name;
	}
}
