<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Tracer;

use ReflectionClass;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Event\TableTraced;
use TheWebSolver\Codegarage\Scraper\Traits\CollectorSource;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer as TraceableTable;

/**
 * @template TInferredColumn
 * @template-implements TraceableTable<TInferredColumn>
 */
abstract class TableTracer implements TraceableTable {
	use CollectorSource;

	public function __construct() {
		$this->collectableFromAttribute( new ReflectionClass( $this ) );
		$this->addEventListener( Table::Row, $this->useCollectedKeysAsTableColumnIndices( ... ) );
	}

	protected function useCollectedKeysAsTableColumnIndices( TableTraced $event ): void {
		( $indices = $this->collectSourceItems() ) && $event->tracer->setItemsIndices( $indices );
	}
}
