<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Event;

use Closure;
use DOMElement;
use LogicException;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Enums\EventAt;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;

class TableTraced {
	/** @placeholder: %s: Table Structure nodeName */
	final public const TRACING_ALREADY_COMPLETE = 'Impossible to stop tracing table structure "<%s>" after tracing is complete';

	/** @var Closure(TableTracer<mixed>, string|DOMElement): void */
	private Closure $taskHandler;
	private bool $shouldStopTracing = false;

	public function __construct( public Table $for, public EventAt $at, public string|DOMElement $target ) {
		$for->eventDispatchable() || throw new LogicException( sprintf( Table::NON_DISPATCHABLE_EVENT, $for->name ) );
	}

	/**
	 * @return array{0:string,1:string} Table structure nodeName and event at.
	 * @phpstan-return array{0:value-of<Table>,1:string}
	 */
	public function scope(): array {
		return [ $this->for->value, $this->at->name ];
	}

	public function isTargeted( EventAt $when, Table $tableStructure ): bool {
		return $when === $this->at && $tableStructure === $this->for;
	}

	/** @param callable(TableTracer<mixed>, string|DOMElement): void $task */
	public function handle( callable $task ): void {
		$this->taskHandler = $task( ... );
	}

	/** @param TableTracer<mixed> $tracer */
	public function handleTask( TableTracer $tracer ): void {
		isset( $this->taskHandler ) && ( $this->taskHandler )( $tracer, $this->target );
	}

	/** @throws LogicException When unstoppable table structure or event at. */
	public function stopTracing( bool $stop = true ): void {
		$this->shouldStopTracing = $stop && $this->assertEventIsStoppable();
	}

	public function shouldStopTrace(): bool {
		return $this->shouldStopTracing;
	}

	private function assertEventIsStoppable(): bool {
		EventAt::End === $this->at
			&& throw new LogicException( sprintf( self::TRACING_ALREADY_COMPLETE, $this->for->value ) );

		return $this->for->eventStoppable()
			|| throw new LogicException( sprintf( Table::NON_STOPPABLE_EVENT, $this->for->name ) );
	}
}
