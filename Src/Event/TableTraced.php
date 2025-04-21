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
	final public const TRACING_NOT_STOPPABLE = 'Impossible to stop tracing table structure "<%s>" after tracing is complete';

	/** @var Closure(TableTracer<mixed>, string|DOMElement): void */
	private Closure $taskHandler;
	private bool $shouldStopTracing = false;

	public function __construct(
		public Table $for,
		public EventAt $at,
		public string|DOMElement $target
	) {}

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

	/** @throws LogicException When this method is called when `EventAt::End`. */
	public function stopTracing( bool $stop = true ): void {
		EventAt::End === $this->at
			&& throw new LogicException( sprintf( self::TRACING_NOT_STOPPABLE, $this->for->value ) );

		$this->shouldStopTracing = $stop;
	}

	public function shouldStopTrace(): bool {
		return $this->shouldStopTracing;
	}
}
