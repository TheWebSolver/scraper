<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits\Table;

use Closure;
use DOMNode;
use Iterator;
use Throwable;
use BackedEnum;
use DOMElement;
use ArrayObject;
use SplFixedArray;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Enums\EventAt;
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
	/** @var array<array-key,array<value-of<Table>,array<string,bool>>> */
	private array $discoveredTable__eventListenersFired = [];
	/** @var array{0:Table,1:EventAt} */
	private array $discoveredTable__eventListenerFiring;

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
		if ( ! $this->isCurrentlyListening( EventAt::Start, for: Table::Row ) ) {
			$placeholders = [ static::class, __FUNCTION__, Table::class, Table::Row->name ];

			throw new ScraperError(
				sprintf( self::USE_EVENT_LISTENER, ...[ ...$placeholders, 'set column names.' ] )
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

	protected function dispatchEventListenerForTable( int|string $id, string|DOMElement $body ): void {
		if ( ! in_array( $id, $this->discoveredTable__ids, true ) ) {
			$this->discoveredTable__ids[] = $this->currentTable__id = $id;
		}

		$this->fireEventListenerOf( Table::TBody, EventAt::Start, $body );
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

		$this->discoveredTable__eventListenersFired = [];
	}

	public function resetTableTraced(): void {
			$this->discoveredTable__excludedStructures = [];
			$this->discoveredTable__captions           = [];
			$this->discoveredTable__headNames          = [];
			$this->discoveredTable__rows               = [];
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

	/** @return ?Closure(static, string|DOMElement): mixed */
	private function getEventListenersOf( Table $table, EventAt $eventAt ): ?Closure {
		$listeners = $this->discoveredTable__eventListeners[ $table->value ] ?? null;

		return $listeners[ $eventAt->name ] ?? null;
	}

	/**
	 * @param Closure(static, string|DOMElement): mixed $callback
	 * @throws Throwable When user throws exception within callback.
	 */
	private function tryFiringEventListenerWith( Closure $callback, string|DOMElement $node ): void {
		try {
			$callback( $this, $node );
		} catch ( Throwable $e ) {
			unset( $this->discoveredTable__eventListenerFiring );

			throw $e;
		}
	}

	private function fireEventListenerOf( Table $table, EventAt $eventAt, string|DOMElement $node ): void {
		$callback       = $this->getEventListenersOf( $table, $eventAt );
		$id             = $this->currentTable__id;
		$firedPositions = $this->discoveredTable__eventListenersFired[ $id ][ $table->value ] ?? [
			EventAt::Start->name => false,
			EventAt::End->name   => false,
		];

		if ( ! $callback ) {
			$this->discoveredTable__eventListenersFired[ $id ][ $table->value ] = $firedPositions;

			return;
		}

		$firedPositions[ $eventAt->name ]           = true;
		$this->discoveredTable__eventListenerFiring = [ $table, $eventAt ];

		$this->tryFiringEventListenerWith( $callback, $node );

		unset( $this->discoveredTable__eventListenerFiring );

		$this->discoveredTable__eventListenersFired[ $id ][ $table->value ] = $firedPositions;
	}

	private function isCurrentlyListening( EventAt $eventAt, Table $for ): bool {
		if ( ! isset( $this->discoveredTable__eventListenerFiring ) ) {
			return false;
		}

		[$firingTable, $firingAt] = $this->discoveredTable__eventListenerFiring;

		return $firingTable === $for && $firingAt === $eventAt;
	}

	private function hasFiredEventListenerFor( Table $table, EventAt $eventAt ): bool {
		$listeners = $this->discoveredTable__eventListenersFired;
		$firedAt   = $listeners[ $this->currentTable__id ][ $table->value ] ?? [];

		return $firedAt[ $eventAt->name ] ?? false;
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
	 *   1 :  int,
	 *   2 :? Transformer<static, CollectionSet<TColumnReturn>|iterable<int,string|DOMNode>>
	 * }
	 */
	private function useCurrentTableBodyDetails(): array {
		return [
			/* headInspected */ false,
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
		$tableId                                      = $this->getTableId( current: true );
		$this->discoveredTable__headNames[ $tableId ] = SplFixedArray::fromArray( $names );

		$this->registerCurrentIterationTableHead( false );
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
