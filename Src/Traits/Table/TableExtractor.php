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
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Traits\CollectorSource;
use TheWebSolver\Codegarage\Scraper\Attributes\CollectUsing;
use TheWebSolver\Codegarage\Scraper\Marshaller\MarshallItem;

/** @template TColumnReturn */
trait TableExtractor {
	use CollectorSource;

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
	private ?TableTraced $discoveredTable__eventBeingDispatched = null;
	/** @var array<value-of<Table>,array{Start?:array<Closure(TableTraced): void>,End?:array<Closure(TableTraced): void>}> */
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
	/** @var Iterator<array-key,ArrayObject<array-key,TColumnReturn>>[] */
	private array $discoveredTable__rows = [];

	private int|string $currentTable__id;
	/** @var CollectUsing[] Column indexes and offset positions */
	private array $currentTable__columnInfo;
	/** @var array<array-key,array<int,array{0:int,1:TColumnReturn}>> */
	private array $currentTable__rowSpan = [];
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

	public function addTransformer( Table $structure, Transformer $transformer ): static {
		$this->discoveredTable__transformers[ $structure->value ] = $transformer;

		return $this;
	}

	public function hasTransformer( Table $structure ): bool {
		return isset( $this->discoveredTable__transformers[ $structure->value ] );
	}

	public function addEventListener( Table $structure, callable $callback, EventAt $eventAt = EventAt::Start ): static {
		$this->discoveredTable__eventListeners[ $structure->value ][ $eventAt->name ][] = $callback( ... );

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
			Table::Column => $this->getCurrentIterationColumnCount(),
			default       => null,
		};
	}

	public function resetTableHooks(): void {
		unset( $this->discoveredTable__transformers );

		$this->discoveredTable__eventBeingDispatched     = null;
		$this->discoveredTable__eventListenersDispatched = $this->discoveredTable__eventListeners = [];
	}

	public function resetTableTraced(): void {
		$this->discoveredTable__excludedStructures = [];
		$this->discoveredTable__captions           = [];
		$this->discoveredTable__headNames          = [];
		$this->discoveredTable__rows               = [];
		$this->currentTable__rowSpan               = [];
		$this->currentTable__datasetCount          = [];
		$this->currentIteration__columnCount       = [];
		$this->currentIteration__rowCount          = [];
	}

	abstract protected function transformCurrentIterationTableHead( mixed $node, Transformer $transformer ): string;

	public function inferTableHeadFrom( iterable $elementList ): void {
		[$names, $skippedNodes, $transformer] = $this->useCurrentTableHeadDetails();

		foreach ( $elementList as $currentIndex => $head ) {
			if ( is_null( $content = $this->tickCurrentHeadIterationSkippedHeadNode( $head ) ) ) {
				++$skippedNodes;

				continue;
			}

			$this->registerCurrentIterationTableHeadPosition( $currentIndex - $skippedNodes );

			$names[] = $transformer ? $this->transformCurrentIterationTableHead( $head, $transformer ) : $content;
		}

		$this->registerCurrentTableHead( $names );
	}

	abstract protected function afterCurrentTableColumnRegistered( mixed $column, mixed $value ): void;

	public function inferTableDataFrom( iterable $elementList ): array {
		[$source, $skippedNodes, $transformer] = $this->useCurrentTableColumnDetails();
		$spannedValues                         = $this->getSpannedRowColumnsValue();
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

			$this->registerCurrentIterationTableColumn( $indexKeys[ $currentPosition ] ?? null, $currentPosition + 1 );
			$this->afterCurrentTableColumnRegistered(
				$column,
				$this->registerCurrentTableColumn( $column, $transformer, $dataset, $currentPosition )
			);

			unset( $this->currentIteration__columnName );
		}//end foreach

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

	/** @param TColumnReturn $value */
	private function registerSpannedRowColumnIn( int $position, int $spanCount, mixed $value ): void {
		$this->currentTable__rowSpan[ $this->getTableId( true ) ][ $position ] = [ $spanCount, $value ];
	}

	private function unregisterSpannedRowColumnIn( int $position ): void {
		unset( $this->currentTable__rowSpan[ $this->getTableId( true ) ][ $position ] );
	}

	/** @param int[] $offsetPositions */
	private function shouldSkipTableColumnIn( int $position, array $offsetPositions ): bool {
		return $offsetPositions && in_array( $position, $offsetPositions, true );
	}

	private function getCurrentIterationColumnCount(): ?int {
		$countUptoCurrent = $this->currentIteration__columnCount[ $this->currentTable__id ] ?? null;

		if ( null === $countUptoCurrent ) {
			return null;
		}

		if ( ! $column = $this->getIndicesSource() ) {
			return $countUptoCurrent;
		}

		$offsetCount = count( $column->offsets ?? [] );

		return $countUptoCurrent > $offsetCount ? $countUptoCurrent - $offsetCount : $countUptoCurrent;
	}

	private function getCurrentTableDatasetCount(): int {
		return $this->currentTable__datasetCount[ $this->getTableId( true ) ] ?? 0;
	}

	/** @return ($position is null ? array<int,array{0:int,1:TColumnReturn}> : array{0:int,1:TColumnReturn}) */
	private function getSpannedRowColumnsValue( ?int $position = null ) {
		$spanned = $this->currentTable__rowSpan[ $this->getTableId( true ) ] ?? [];

		return null === $position ? $spanned : ( $spanned[ $position ] ?? [] );
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
			unset( $this->discoveredTable__eventBeingDispatched );
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

	/** @return array{0:?CollectUsing,1:int,2:Transformer<static,TColumnReturn>} */
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

	/** @return array{isValid:bool,isAllowed:bool,content:?string} */
	abstract protected function useCurrentIterationValidatedHead( mixed $node ): array;

	private function tickCurrentHeadIterationSkippedHeadNode( mixed $node ): ?string {
		$currentHead = $this->useCurrentIterationValidatedHead( $node );

		! $currentHead['isValid']
			&& $this->currentIteration__allTableHeads
			&& ( $this->currentIteration__allTableHeads = $currentHead['isAllowed'] );

		return $currentHead['content'];
	}

	/** @param list<string> $names */
	private function registerCurrentTableHead( array $names ): void {
		$this->registerCurrentIterationTableHeadPosition( false );

		if ( ! $this->currentIteration__allTableHeads || ! $names ) {
			return;
		}

		$headNames = SplFixedArray::fromArray( $names );

		$this->discoveredTable__headNames[ $this->getTableId( current: true ) ] = $headNames;

		$this->registerCurrentTableDatasetCount( $headNames->count() );
	}

	private function registerCurrentIterationTableHeadPosition( int|false $position ): void {
		if ( false === $position ) {
			unset( $this->currentIteration__headCount );

			return;
		}

		$this->currentIteration__headCount = $position + 1;
	}

	/**
	 * @param string[]             $indexKeys
	 * @param array<TColumnReturn> $dataset
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
	 * @return array{0:array<TColumnReturn>,1:int[]} Inserted values and remaining insert positions.
	 */
	private function fromSpannedRowColumnsIn( array $positions, array $indexKeys ): array {
		$insertedValues   = [];
		$insertPositions  = $positions;
		$datasetPositions = range( 0, $this->getCurrentTableDatasetCount() - 1 );

		foreach ( $positions as $key => $position ) {
			if ( ! $spannedRow = $this->getSpannedRowColumnsValue( $position ) ) {
				continue;
			}

			[$spanCount, $spannedValue] = $spannedRow;

			if ( 1 < $spanCount ) {
				$insertedValues[ $indexKeys[ $position ] ?? $position ] = $spannedValue;

				$this->registerCurrentIterationTableColumn( $indexKeys[ $position ] ?? null, $position + 1 );
				$this->registerSpannedRowColumnIn( $position, --$spanCount, $spannedValue );
			} else {
				unset( $insertPositions[ $key ] );

				$this->unregisterSpannedRowColumnIn( $position );
			}
		}

		return [ $insertedValues, array_diff( $datasetPositions, $insertPositions ) ];
	}

	/**
	 * @param int[]                $remainingPositions
	 * @param string[]             $indexKeys
	 * @param array<TColumnReturn> $dataset
	 * @return array<TColumnReturn>
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

	/** @return array{0:?TColumnReturn,1:int} */
	abstract protected function transformCurrentIterationTableColumn( mixed $node, Transformer $transformer ): array;

	/**
	 * @param Transformer<static,TColumnReturn> $transformer
	 * @param array<array-key,TColumnReturn>    $data
	 * @return ?TColumnReturn
	 */
	private function registerCurrentTableColumn(
		mixed $column,
		Transformer $transformer,
		array &$data,
		int $currentPosition
	): mixed {
		[$value, $spanCount] = $this->transformCurrentIterationTableColumn( $column, $transformer );

		$count    = $this->getCurrentIterationCount( Table::Column );
		$position = $count ? $count - 1 : 0;
		$isValid  = null !== $value && '' !== $value;

		if ( $isValid && $spanCount > 1 ) {
			$this->registerSpannedRowColumnIn( $currentPosition, $spanCount, $value );
		}

		return $isValid ? ( $data[ $this->getCurrentItemIndex() ?? $position ] = $value ) : null;
	}

	private function registerCurrentIterationTableColumn( ?string $name, int $count ): void {
		$this->currentIteration__columnCount[ $this->currentTable__id ] = $count;
		$name && $this->currentIteration__columnName                    = $name;
	}

	/** @param int[] $spannedPositions */
	private function registerColumnCountWithMaxValueOf( array $spannedPositions ): void {
		$spannedPositions
			&& ( $max = max( $spannedPositions ) + 1 ) > $this->getCurrentIterationColumnCount()
			&& $this->registerCurrentIterationTableColumn( name: null, count: $max );
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
}
