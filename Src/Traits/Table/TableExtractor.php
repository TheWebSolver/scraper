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
use TheWebSolver\Codegarage\Scraper\Enums\EventAt;
use TheWebSolver\Codegarage\Scraper\Helper\Normalize;
use TheWebSolver\Codegarage\Scraper\Event\TableTraced;
use TheWebSolver\Codegarage\Scraper\Data\CollectionSet;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Marshaller\MarshallItem;

/** @template TColumnReturn */
trait TableExtractor {
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
	/** @var array<value-of<Table>,array{Start?:Closure(TableTraced): void,End?:Closure(TableTraced): void}> */
	private array $discoveredTable__eventListeners;
	private ?TableTraced $discoveredTable__eventBeingDispatched = null;
	/** @var array<array-key,array<value-of<Table>,array{0:bool,1:array<string,bool>}>> */
	private array $discoveredTable__eventListenersDispatched = [];

	/** @var (int|string)[] */
	private array $discoveredTable__ids = [];

	/** @var list<Table> */
	private array $discoveredTable__excludedStructures = [];
	/** @var (string|null)[] */
	private array $discoveredTable__captions = [];
	/** @var SplFixedArray<string>[] */
	private array $discoveredTable__headNames = [];
	/** @var Iterator<array-key,ArrayObject<array-key,TColumnReturn>>[] */
	private array $discoveredTable__rows = [];

	private int|string $currentTable__id;
	/** @var array{0:array<int,string>,1:array<int,int>,2:int}[] Names, offsets, & last index */
	private array $currentTable__columnInfo;

	/** @var int[] */
	private array $currentIteration__rowCount = [];
	/** @var int[] */
	private array $currentIteration__columnCount  = [];
	private bool $currentIteration__allTableHeads = true;
	private string $currentIteration__columnName;
	private int $currentIteration__headCount;

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

	public function addEventListener( Table $for, callable $callback, EventAt $eventAt = EventAt::Start ): static {
		$this->discoveredTable__eventListeners[ $for->value ][ $eventAt->name ] = $callback( ... );

		return $this;
	}

	public function setItemsIndices( array $keys, int ...$offset ): void {
		if ( ! $this->isInvokedByEventListenerOf( Table::Row, EventAt::Start ) ) {
			$placeholders = [ static::class, __FUNCTION__, Table::class, Table::Row->name, EventAt::class, EventAt::Start->name ];

			throw new ScraperError(
				sprintf( TableTracer::USE_EVENT_LISTENER, ...[ ...$placeholders, 'set column names.' ] )
			);
		}

		$keys && $this->currentTable__columnInfo[ $this->currentTable__id ] = Normalize::listWithOffset( $keys, $offset );
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
		return $this->currentTable__columnInfo[ $this->currentTable__id ][0] ?? [];
	}

	public function getCurrentItemIndex(): ?string {
		return $this->currentIteration__columnName ?? null;
	}

	public function getCurrentIterationCount( ?BackedEnum $type = null ): ?int {
		if ( ! isset( $this->currentTable__id ) ) {
			return null;
		}

		return match ( $type ) {
			Table::Head   => $this->currentIteration__headCount ?? null,
			Table::Row    => $this->currentIteration__rowCount[ $this->currentTable__id ] ?? null,
			Table::Column => $this->getCurrentIterationColumnCount(),
			default       => null,
		};
	}

	public function resetTableHooks(): void {
		unset(
			$this->discoveredTable__transformers,
			$this->discoveredTable__eventListeners,
			$this->discoveredTable__eventBeingDispatched,
		);

		$this->discoveredTable__eventListenersDispatched = [];
	}

	public function resetTableTraced(): void {
			$this->discoveredTable__excludedStructures = [];
			$this->discoveredTable__captions           = [];
			$this->discoveredTable__headNames          = [];
			$this->discoveredTable__rows               = [];
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

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- To be used by exhibiting class.
	protected function isTargetedTable( string|DOMElement $node ): bool {
		return true;
	}

	private function tableColumnsExistInBody( string|DOMElement $node ): bool {
		return match ( true ) {
			$node instanceof DOMElement => ! ! $node->getElementsByTagName( 'td' )->length,
			default                     => str_contains( $node, '<td' ) && str_contains( $node, '</td>' ),
		};
	}

	private function getCurrentIterationColumnCount(): ?int {
		$countUptoCurrent = $this->currentIteration__columnCount[ $this->currentTable__id ] ?? null;

		if ( null === $countUptoCurrent ) {
			return null;
		}

		if ( ! $column = $this->currentTable__columnInfo[ $this->currentTable__id ] ?? null ) {
			return $countUptoCurrent;
		}

		$offsetCount = count( $column[1] );

		return $countUptoCurrent > $offsetCount ? $countUptoCurrent - $offsetCount : $countUptoCurrent;
	}

	/** @return ?Closure(TableTraced): void */
	private function getEventListener( TableTraced $event ): ?Closure {
		[$nodeName, $when] = $event->scope();

		return $this->discoveredTable__eventListeners[ $nodeName ][ $when ] ?? null;
	}

	/** @param Closure(TableTraced): void $listenTo */
	private function tryListeningToDispatchedEvent( TableTraced $event, Closure $listenTo ): void {
		try {
			$this->discoveredTable__eventBeingDispatched = $event;

			$listenTo( $event );
		} finally {
			unset( $this->discoveredTable__eventBeingDispatched );
		}
	}

	private function dispatchEvent( TableTraced $event ): void {
		$listener            = $this->getEventListener( $event );
		$id                  = $this->currentTable__id;
		[$tagName, $eventAt] = $event->scope();
		$whenDispatched      = $this->discoveredTable__eventListenersDispatched[ $id ][ $tagName ] ?? [
			$event->shouldStopTrace(),
			[
				EventAt::Start->name => false,
				EventAt::End->name   => false,
			],
		];

		if ( ! $listener ) {
			$this->discoveredTable__eventListenersDispatched[ $id ][ $tagName ] = $whenDispatched;

			return;
		}

		$whenDispatched[1][ $eventAt ]                                      = true;
		$this->discoveredTable__eventListenersDispatched[ $id ][ $tagName ] = $whenDispatched;

		$this->tryListeningToDispatchedEvent( $event, $listener );
	}

	private function dispatchEventForTable( int|string $id, string|DOMElement $body ): void {
		if ( ! in_array( $id, $this->discoveredTable__ids, true ) ) {
			$this->discoveredTable__ids[] = $this->currentTable__id = $id;
		}

		$this->dispatchEvent( new TableTraced( Table::TBody, EventAt::Start, $body, $this ) );
	}

	private function isInvokedByEventListenerOf( Table $structure, EventAt $when ): bool {
		return $this->discoveredTable__eventBeingDispatched?->isTargeted( $when, $structure ) ?? false;
	}

	/** @return array{0:?bool,1:?array<string,bool>} **0:** Whether event was stopped, **1:** EventAt */
	private function getDispatchedEventStatus( Table $structure ): array {
		$id        = $this->currentTable__id;
		$listeners = $this->discoveredTable__eventListenersDispatched;

		return $listeners[ $id ][ $structure->value ] ?? [ null, null ];
	}

	/** @return array{0:array<int,string>,1:array<int,int>,2:?int,3:int,4:Transformer<static,TColumnReturn>} */
	private function useCurrentTableColumnDetails(): array {
		$columns = $this->currentTable__columnInfo[ $this->currentTable__id ] ?? [];

		return [
			/* columnNames  */ $columns[0] ?? [],
			/* offset       */ $columns[1] ?? [],
			/* lastPosition */ $columns[2] ?? null,
			/* skippedNodes */ $this->currentIteration__columnCount[ $this->currentTable__id ] = 0,
			/* transformer  */ $this->discoveredTable__transformers[ Table::Column->value ] ?? new MarshallItem(),
		];
	}

	/** @return array{0:list<string>,1:int,2:?Transformer<static,string>} */
	private function useCurrentTableHeadDetails(): array {
		return [
			/* names        */ [],
			/* skippedNodes */ 0,
			/* transformer  */ $this->discoveredTable__transformers[ Table::Head->value ] ?? null,
		];
	}

	/**
	 * @return array{
	 *   0 :  bool,
	 *   1 : bool,
	 *   2 :  int,
	 *   3 :? Transformer<static, CollectionSet<TColumnReturn>|iterable<int,string|DOMNode>>
	 * }
	 */
	private function useCurrentTableBodyDetails(): array {
		return [
			/* headInspected */ false,
			/* bodyStarted   */ false,
			/* position      */ $this->currentIteration__rowCount[ $this->currentTable__id ] = 0,
			/* transformer   */ $this->discoveredTable__transformers[ Table::Row->value ] ?? null,
		];
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
		$this->registerCurrentIterationTableHead( false );

		if ( ! $this->currentIteration__allTableHeads || ! $names ) {
			return;
		}

		$tableId                                      = $this->getTableId( current: true );
		$this->discoveredTable__headNames[ $tableId ] = SplFixedArray::fromArray( $names );
	}

	private function registerCurrentIterationTableHead( int|false $position ): void {
		if ( false === $position ) {
			unset( $this->currentIteration__headCount );

			return;
		}

		$this->currentIteration__headCount = $position + 1;
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
		$count    = $this->getCurrentIterationCount( Table::Column );
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
