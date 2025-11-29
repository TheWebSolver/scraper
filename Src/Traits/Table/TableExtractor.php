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
use LogicException;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Enums\EventAt;
use TheWebSolver\Codegarage\Scraper\Data\EmptyItem;
use TheWebSolver\Codegarage\Scraper\Data\TableCell;
use TheWebSolver\Codegarage\Scraper\Data\TableHead;
use TheWebSolver\Codegarage\Scraper\Helper\Normalize;
use TheWebSolver\Codegarage\Scraper\Event\TableTraced;
use TheWebSolver\Codegarage\Scraper\Data\CollectionSet;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Traits\CollectorSource;
use TheWebSolver\Codegarage\Scraper\Attributes\CollectUsing;
use TheWebSolver\Codegarage\Scraper\Marshaller\MarshallItem;

/** @template TableColumnValue */
trait TableExtractor {
	use CollectorSource;

	private bool $shouldPerform__allTableDiscovery = false;
	private mixed $tracedElements__targetedTable   = null;

	/**
	 * @var array{
	 *   tr      ?: Transformer<static, CollectionSet<TableColumnValue>|iterable<int,string|DOMNode>>,
	 *   caption ?: Transformer<static, string>,
	 *   th      ?: Transformer<static, string>,
	 *   td      ?: Transformer<static, TableColumnValue>,
	 * }
	 */
	private array $discoveredTable__transformers;
	private ?TableTraced $discoveredTable__eventBeingDispatched = null;
	/** @var array<value-of<Table>,array{Start?:array<Closure(TableTraced):void>,End?:array<Closure(TableTraced):void>}> */
	private array $discoveredTable__eventListeners = [];
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
	/** @var Iterator<array-key,ArrayObject<array-key,TableColumnValue>>[] */
	private array $discoveredTable__rows = [];

	private int|string $currentTable__id;
	/** @var CollectUsing[] Column indexes and offset positions */
	private array $currentTable__columnInfo;
	/** @var array<array-key,array<int,TableCell<TableColumnValue>>> */
	private array $currentTable__extendedColumns = [];
	/** @var int[] */
	private array $currentTable__datasetCount = [];

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

	public function traceWithout( Table ...$structures ): static {
		$this->discoveredTable__excludedStructures = $structures;

		return $this;
	}

	public function addTransformer( Transformer $transformer, ?BackedEnum $structure = null ): static {
		$this->assertIsTable( $structure, 'adding transformer' );

		$this->discoveredTable__transformers[ $structure->value ] = $transformer;

		return $this;
	}

	public function hasTransformer( ?BackedEnum $structure = null ): bool {
		$this->assertIsTable( $structure, 'checking transformer' );

		return isset( $this->discoveredTable__transformers[ $structure->value ] );
	}

	public function addEventListener( callable $listener, ?BackedEnum $structure = null, EventAt $eventAt = EventAt::Start ): static {
		$this->assertIsTable( $structure, 'adding event listener' );

		$this->discoveredTable__eventListeners[ $structure->value ][ $eventAt->name ][] = $listener( ... );

		return $this;
	}

	public function setIndicesSource( CollectUsing $collection ): void {
		if ( $this->isInvokedByEventListenerOf( Table::Row, EventAt::Start ) ) {
			$this->registerColumnIndicesSource( $collection );

			return;
		}

		$values = [ Normalize::case( Table::Row ), Normalize::case( EventAt::Start ), 'set column names.' ];

		$this->throwEventListenerNotUsed( __FUNCTION__, ...$values );
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

	public function getData(): Iterator {
		return $this->discoveredTable__rows[ $id = $this->getTableId( true ) ] ?? throw ScraperError::withSourceMsg(
			'Table Dataset Iterator not found for table ID: "%s". Maybe this is used again after reset?',
			static::class,
			$id
		);
	}

	public function getIndicesSource(): ?CollectUsing {
		return $this->currentTable__columnInfo[ $this->currentTable__id ?? null ] ?? null;
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
			Table::Column => $this->currentIteration__columnCount[ $this->currentTable__id ] ?? null,
			default       => null,
		};
	}

	public function resetHooks(): void {
		unset( $this->discoveredTable__transformers );

		$this->discoveredTable__eventBeingDispatched     = null;
		$this->discoveredTable__eventListenersDispatched = $this->discoveredTable__eventListeners = [];
	}

	public function resetTraced(): void {
		$this->tracedElements__targetedTable       = null;
		$this->discoveredTable__excludedStructures = [];
		$this->discoveredTable__captions           = [];
		$this->discoveredTable__headNames          = [];
		$this->discoveredTable__rows               = [];
		$this->currentTable__extendedColumns       = [];
		$this->currentTable__datasetCount          = [];
		$this->currentIteration__columnCount       = [];
		$this->currentIteration__rowCount          = [];
	}

	abstract protected function transformCurrentIterationTableHead( mixed $node, Transformer $transformer ): string;

	public function inferTableHeadFrom( iterable $elementList ): void {
		[$dataset, $skippedNodes, $transformer] = $this->useCurrentTableHeadDetails();

		foreach ( $elementList as $currentIndex => $head ) {
			if ( is_null( $content = $this->tickCurrentHeadIterationSkippedHeadNode( $head ) ) ) {
				++$skippedNodes;

				continue;
			}

			$this->registerCurrentIterationTableHeadCount( $currentIndex - $skippedNodes );

			$dataset[] = $transformer ? $this->transformCurrentIterationTableHead( $head, $transformer ) : $content;
		}

		$this->registerCurrentTableHead( $dataset );
	}

	abstract protected function afterCurrentTableColumnRegistered( mixed $column, mixed $value ): void;

	public function inferTableDataFrom( iterable $elementList ): array {
		[$source, $skippedNodes, $transformer] = $this->useCurrentTableColumnDetails();
		$spannedValues                         = $this->getSpannedRowColumnsValues();
		$spannedPositions                      = $spannedValues ? array_keys( $spannedValues ) : [];
		$indexKeys                             = $source->items ?? [];
		$lastPosition                          = array_key_last( $indexKeys );
		$remainingPositions                    = $dataset = [];

		if ( $this->getCurrentTableDatasetCount() ) {
			[$dataset, $remainingPositions] = $this->fromSpannedRowColumnsIn( $spannedPositions, $indexKeys );
		}

		foreach ( $elementList as $currentIndex => $column ) {
			if ( ! $this->isTableColumnStructure( $column ) ) {
				++$skippedNodes;

				continue;
			}

			$actualPosition  = $currentIndex - $skippedNodes;
			$currentPosition = $remainingPositions ? array_shift( $remainingPositions ) : $actualPosition;

			if ( $this->shouldSkipTableColumnIn( $currentPosition, $source->offsets ?? [] ) ) {
				continue;
			}

			if ( $this->hasColumnReachedAtLastPosition( $currentPosition, $lastPosition ) ) {
				$remainingPositions = [];

				break;
			}

			$this->registerCurrentTableColumnCount( $currentPosition, $indexKeys[ $currentPosition ] ?? null );
			$this->afterCurrentTableColumnRegistered(
				$column,
				$this->registerCurrentTableColumn( $column, $transformer, $dataset )
			);

			unset( $this->currentIteration__columnName );
		}//end foreach

		foreach ( $remainingPositions as $emptyItemPosition ) {
			$this->registerCurrentTableColumnCount( $emptyItemPosition, $indexKeys[ $emptyItemPosition ] ?? null );
			$this->afterCurrentTableColumnRegistered( new EmptyItem(), $this->registerCurrentTableColumn( new EmptyItem(), $transformer, $dataset ) );
		}

		$this->sortCurrentRowDatasetBy( $indexKeys, $dataset );
		$this->registerColumnCountWithMaxValueOf( $spannedPositions );

		return $this->withEmptyItemsIn( $remainingPositions, $indexKeys, $dataset );
	}

	abstract protected function getTagnameFrom( mixed $node ): mixed;

	protected function isTableColumnStructure( mixed $node ): bool {
		$nodeName = $this->getTagnameFrom( $node );

		return ( $nodeName && ( Table::Head->value === $nodeName || Table::Column->value === $nodeName ) );
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- To be used by exhibiting class.
	protected function isTargetedTable( string|DOMElement $node ): bool {
		return true;
	}

	private function registerTargetedTable( mixed $element ): void {
		$this->tracedElements__targetedTable = $element;
	}

	private function targetIsCurrentTable( mixed $element, bool $inAllTables = false ): bool {
		$currentTableIsTargeted = $this->tracedElements__targetedTable === $element;

		return ( $inAllTables && $currentTableIsTargeted )
			|| ( ! $this->shouldPerform__allTableDiscovery && $currentTableIsTargeted );
	}

	private function hydrateIndicesSourceFromAttribute(): void {
		$this->getIndicesSource() || $this->registerColumnIndicesSource();
	}

	/** @param ?CollectUsing $collection */
	private function registerColumnIndicesSource( ?CollectUsing $collection = null ): void {
		$collection ??= $this->collectableFromAttribute();

		$collection && ( $this->currentTable__columnInfo[ $this->currentTable__id ] = $collection );
	}

	private function registerCurrentTableDatasetCount( int $count ): void {
		$this->currentTable__datasetCount[ $this->getTableId( true ) ] ??= $count;
	}

	/** @param TableCell<TableColumnValue> $cell */
	private function registerExtendableTableColumn( TableCell $cell ): void {
		$this->currentTable__extendedColumns[ $this->getTableId( true ) ][ $cell->position ] = $cell;
	}

	private function unregisterSpannedRowColumnIn( int $position ): void {
		unset( $this->currentTable__extendedColumns[ $this->getTableId( true ) ][ $position ] );
	}

	/** @param int[] $offsetPositions */
	private function shouldSkipTableColumnIn( int $position, array $offsetPositions ): bool {
		return $offsetPositions && in_array( $position, $offsetPositions, true );
	}

	private function getCurrentTableDatasetCount(): int {
		return $this->currentTable__datasetCount[ $this->getTableId( true ) ] ?? 0;
	}

	/** @return ($position is null ? array<int,TableCell<TableColumnValue>> : TableCell<TableColumnValue>) */
	private function getSpannedRowColumnsValues( ?int $position = null ) {
		$spannedColumns = $this->currentTable__extendedColumns[ $this->getTableId( true ) ] ?? [];

		return null === $position ? $spannedColumns : ( $spannedColumns[ $position ] ?? [] );
	}

	/** @return ?array<Closure(TableTraced): void> */
	private function getEventListenersFor( TableTraced $event ): ?array {
		[$nodeName, $when] = $event->scope();

		return $this->discoveredTable__eventListeners[ $nodeName ][ $when ] ?? null;
	}

	/** @param array<Closure(TableTraced): void> $listeners */
	private function tryListeningToDispatchedEvent( TableTraced $event, array $listeners ): void {
		try {
			$this->discoveredTable__eventBeingDispatched = $event;

			foreach ( $listeners as $listenTo ) {
				$listenTo( $event );
			}
		} finally {
			$this->discoveredTable__eventBeingDispatched = null;
		}
	}

	private function dispatchEvent( TableTraced $event ): void {
		$listeners           = $this->getEventListenersFor( $event );
		$id                  = $this->currentTable__id;
		[$tagName, $eventAt] = $event->scope();
		$whenDispatched      = $this->discoveredTable__eventListenersDispatched[ $id ][ $tagName ] ?? [
			$event->shouldStopTrace(),
			[
				EventAt::Start->name => false,
				EventAt::End->name   => false,
			],
		];

		if ( ! $listeners ) {
			$this->discoveredTable__eventListenersDispatched[ $id ][ $tagName ] = $whenDispatched;

			return;
		}

		$whenDispatched[1][ $eventAt ]                                      = true;
		$this->discoveredTable__eventListenersDispatched[ $id ][ $tagName ] = $whenDispatched;

		$this->tryListeningToDispatchedEvent( $event, $listeners );
	}

	private function dispatchEventForTable( int|string $id, string|DOMElement $body ): void {
		if ( ! in_array( $id, $this->discoveredTable__ids, true ) ) {
			$this->discoveredTable__ids[] = $this->currentTable__id = $id;
		}

		$this->dispatchEvent( new TableTraced( Table::TBody, EventAt::Start, $body, $this ) );
	}

	private function isInvokedByEventListenerOf( Table $structure, EventAt $eventAt ): bool {
		return $this->discoveredTable__eventBeingDispatched?->isTargeted( $eventAt, $structure ) ?? false;
	}

	/** @return array{0:?bool,1:?array<string,bool>} **0:** Whether event was stopped, **1:** EventAt */
	private function getDispatchedEventStatus( Table $structure ): array {
		$id        = $this->currentTable__id;
		$listeners = $this->discoveredTable__eventListenersDispatched;

		return $listeners[ $id ][ $structure->value ] ?? [ null, null ];
	}

	/** @return array{0:?CollectUsing,1:int,2:Transformer<static,TableColumnValue>} */
	private function useCurrentTableColumnDetails(): array {
		return [
			/* Source       */ $this->getIndicesSource(),
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

	/** @return array{0:bool,1:bool,2:int} */
	private function useCurrentTableBodyDetails(): array {
		return [
			/* headInspected */ false,
			/* bodyStarted   */ false,
			/* position      */ $this->currentIteration__rowCount[ $this->currentTable__id ] = 0,
		];
	}

	private function shouldTraceTableStructure( Table $structure ): bool {
		return ! in_array( $structure, $this->discoveredTable__excludedStructures, strict: true );
	}

	private function hasColumnReachedAtLastPosition( int $currentPosition, ?int $lastPosition ): bool {
		return null !== $lastPosition && $currentPosition > $lastPosition;
	}

	abstract protected function useCurrentIterationValidatedHead( mixed $node ): TableHead;

	private function tickCurrentHeadIterationSkippedHeadNode( mixed $node ): ?string {
		$head = $this->useCurrentIterationValidatedHead( $node );

		! $head->isValid
			&& $this->currentIteration__allTableHeads
			&& ( $this->currentIteration__allTableHeads = $head->isAllowed );

		return $head->value;
	}

	/** @param list<string> $names */
	private function registerCurrentTableHead( array $names ): void {
		$this->registerCurrentIterationTableHeadCount( false );

		if ( ! $this->currentIteration__allTableHeads || ! $names ) {
			return;
		}

		$headNames = SplFixedArray::fromArray( $names );

		$this->discoveredTable__headNames[ $this->getTableId( current: true ) ] = $headNames;

		$this->registerCurrentTableDatasetCount( $headNames->count() );
	}

	private function registerCurrentIterationTableHeadCount( int|false $position ): void {
		if ( false === $position ) {
			unset( $this->currentIteration__headCount );

			return;
		}

		$this->currentIteration__headCount = $position + 1;
	}

	/**
	 * @param string[]                $indexKeys
	 * @param array<TableColumnValue> $dataset
	 */
	private function sortCurrentRowDatasetBy( array $indexKeys, array &$dataset ): void {
		if ( ! empty( $indexKeys ) && ( $items = array_flip( $indexKeys ) ) ) {
			uksort( $dataset, callback: static fn ( string $a, string $b ): int => $items[ $a ] <=> $items[ $b ] );
		} else {
			ksort( $dataset, flags: SORT_NUMERIC );
		}
	}

	/**
	 * @param int[]    $positions Columns' position in previously spanned row.
	 * @param string[] $indexKeys The mappable index keys.
	 * @return array{0:array<TableColumnValue>,1:int[]} Inserted values and remaining insert positions.
	 */
	private function fromSpannedRowColumnsIn( array $positions, array $indexKeys ): array {
		$insertedValues  = [];
		$insertPositions = $positions;
		$lastPosition    = ( $items = $this->getIndicesSource()?->items )
			? array_key_last( $items )
			: $this->getCurrentTableDatasetCount() - 1;

		foreach ( $positions as $key => $position ) {
			if ( ! $cell = $this->getSpannedRowColumnsValues( $position ) ) {
				continue;
			}

			if ( $cell->shouldExtendToNextRow() ) {
				$insertedValues[ $indexKeys[ $position ] ?? $position ] = $cell->value;

				$this->registerCurrentTableColumnCount( $position, $indexKeys[ $position ] ?? null );
				$this->registerExtendableTableColumn( $cell->withRemainingRowExtension() );
			} else {
				unset( $insertPositions[ $key ] );

				$this->unregisterSpannedRowColumnIn( $position );
			}
		}

		return [ $insertedValues, array_diff( range( 0, $lastPosition ), $insertPositions ) ];
	}

	/**
	 * @param int[]                   $remainingPositions
	 * @param string[]                $indexKeys
	 * @param array<TableColumnValue> $dataset
	 * @return array<TableColumnValue>
	 */
	private function withEmptyItemsIn( array $remainingPositions, array $indexKeys, array $dataset ): array {
		foreach ( $remainingPositions as $emptyPosition ) {
			$dataset[ $indexKeys[ $emptyPosition ] ?? $emptyPosition ] = 'N/A';
		}

		return $dataset;
	}

	private function registerCurrentIterationTableRow( int $count ): void {
		$this->currentIteration__rowCount[ $this->currentTable__id ] = $count;
	}

	abstract protected function transformCurrentIterationTableColumn( mixed $node, Transformer $transformer ): TableCell;

	/**
	 * @param Transformer<static,TableColumnValue> $transformer
	 * @param array<array-key,TableColumnValue>    $data
	 * @return ?TableColumnValue
	 */
	private function registerCurrentTableColumn( mixed $column, Transformer $transformer, array &$data ): mixed {
		$position = ( $count = $this->getCurrentIterationCount( Table::Column ) ) ? $count - 1 : 0;
		$cell     = $this->transformCurrentIterationTableColumn( $column, $transformer );

		if ( ( $valueValid = $cell->hasValidValue() ) && $cell->shouldExtendToNextRow() ) {
			$this->registerExtendableTableColumn( $cell->withPositionAt( $position ) );
		}

		return $valueValid ? ( $data[ $this->getCurrentItemIndex() ?? $position ] = $cell->value ) : null;
	}

	private function registerCurrentTableColumnCount( int $position, ?string $indexedBy = null ): void {
		$this->currentIteration__columnCount[ $this->currentTable__id ] = $position + 1;
		$indexedBy && $this->currentIteration__columnName               = $indexedBy;
	}

	/** @param int[] $spannedPositions */
	private function registerColumnCountWithMaxValueOf( array $spannedPositions ): void {
		$spannedPositions
			&& ( ( $max = max( $spannedPositions ) ) + 1 ) > $this->getCurrentIterationCount( Table::Column )
			&& $this->registerCurrentTableColumnCount( $max );
	}

	private function throwEventListenerNotUsed( string $methodName, string ...$placeholders ): never {
		$method = static::class . '::' . $methodName;

		throw new ScraperError( sprintf( TableTracer::USE_EVENT_LISTENER, $method, ...$placeholders ) );
	}

	private function throwUnsupportedNodeType( mixed $type, string $exhibit ): never {
		throw new InvalidSource(
			sprintf( 'Unsupported type: "%1$s" provided when using trait "%2$s".', get_debug_type( $type ), $exhibit )
		);
	}

	/**
	 * @param ?BackedEnum<string|int> $structure
	 * @throws LogicException When given structure is not table.
	 * @phpstan-assert =Table $structure
	 */
	private function assertIsTable( ?BackedEnum $structure, string $condition ): void {
		$structure instanceof Table || throw new LogicException( sprintf( TableTracer::NO_TABLE_STRUCTURE_PROVIDED, $condition ) );
	}
}
