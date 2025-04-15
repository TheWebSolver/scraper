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
	/** @placeholder `1:` static classname, `2:` throwing methodname, `3:` reason. */
	final public const USE_EVENT_DISPATCHER = 'Calling "%1$s::%2$s()" is not allowed before table is discovered. Use event listener to %3$s';

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

	public function setTracedItemsIndices( array $keys, int|string $id, int ...$offset ): void {
		( $id && $this->getTableId( current: true ) === $id ) || throw new ScraperError(
			sprintf( self::USE_EVENT_DISPATCHER, static::class, __FUNCTION__, 'set column names.' )
		);

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
	public function getTracedItemsIndices(): array {
		return $this->currentTable__columnInfo[ $this->currentTable__id ][0] ?? array();
	}

	public function getCurrentTracedItemIndex(): ?string {
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

		// TODO: implement finish event listener.
		[$fireStartEvent] = $this->getEventListenersRegisteredFor( Table::TBody );

		$fireStartEvent && ( $fireStartEvent )( $this, $body );
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- To be used by exhibiting class.
	protected function isTargetedTable( string|DOMElement $node ): bool {
		return true;
	}

	public function resetTableHooks(): void {
		unset(
			$this->discoveredTable__transformers,
			$this->discoveredTable__eventListeners
		);
	}

	public function resetTableTraced(): void {
			$this->discoveredTable__excludedStructures = array();
			$this->discoveredTable__captions           = array();
			$this->discoveredTable__headNames          = array();
			$this->discoveredTable__rows               = array();
	}

	/** @return array{0:?Closure(static, string|DOMElement): mixed,1:?Closure(static, string|DOMElement): mixed} */
	private function getEventListenersRegisteredFor( Table $table ): array {
		$listeners = $this->discoveredTable__eventListeners[ $table->value ] ?? null;

		return array( $listeners[0] ?? null, $listeners[1] ?? null );
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
	 * @return array{
	 *   0 :  list<string>,
	 *   1 :  int,
	 *   2 :? Transformer<static,string>,
	 *   3 :  array{0:?Closure(static, string|DOMElement): mixed,1:?Closure(static, string|DOMElement): mixed}
	 * }
	 */
	private function useCurrentTableHeadDetails(): array {
		return array(
			$names         = array(),
			$skippedNodes  = 0,
			$thTransformer = $this->discoveredTable__transformers[ Table::Head->value ] ?? null,
			$eventListener = $this->getEventListenersRegisteredFor( Table::THead ),
		);
	}

	/**
	 * @return array{
	 *   0 :  bool,
	 *   1 :  int,
	 *   2 :? Transformer<static, CollectionSet<TColumnReturn>|iterable<int,string|DOMNode>>,
	 *   3 :  array{0:?Closure(static, string|DOMElement): mixed,1:?Closure(static, string|DOMElement): mixed}
	 * }
	 */
	private function useCurrentTableBodyDetails(): array {
		return array(
			$headInspected  = false,
			$position       = $this->currentIteration__rowCount[ $this->currentTable__id ] = 0,
			$transformer    = $this->discoveredTable__transformers[ Table::Row->value ] ?? null,
			$eventListeners = $this->getEventListenersRegisteredFor( Table::Row ),
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
			? ( $data[ $this->getCurrentTracedItemIndex() ?? $position ] = $value )
			: null;
	}

	private function registerCurrentIterationTableColumn( ?string $name, int $count ): void {
		$this->currentIteration__columnCount[ $this->currentTable__id ] = $count;
		$name && $this->currentIteration__columnName                    = $name;
	}
}
