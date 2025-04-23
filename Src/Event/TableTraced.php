<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Event;

use DOMElement;
use LogicException;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Enums\EventAt;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;

final class TableTraced {
	/** @placeholder: %s: Table Structure nodeName */
	public const TRACING_ALREADY_COMPLETE = 'Impossible to stop tracing table structure "<%s>" after tracing is complete';

	/**
	 * @param TableTracer<mixed> $tracer
	 * @throws LogicException When event is created for unsupported table structure.
	 */
	public function __construct(
		public readonly Table $for,
		public readonly EventAt $at,
		public readonly string|DOMElement $target,
		public readonly TableTracer $tracer,
		private bool $shouldStopTracing = false
	) {
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

	/** @throws LogicException When unstoppable table structure or event at. */
	public function stopTracing(): void {
		$this->shouldStopTracing = $this->assertEventIsStoppable();
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
