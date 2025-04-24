<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Fixture\Table;

use Iterator;
use ArrayObject;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;

/** @template TTracer of TableTracer<string> */
trait TraceATable {
	/** @var TTracer */
	protected TableTracer $tracer;

	/** @return TTracer */
	public function getTableTracer(): TableTracer {
		return $this->tracer;
	}

	/** @return Iterator<array-key,ArrayObject<array-key,string>> */
	protected function currentTableIterator( string $content, bool $normalize = true ): Iterator {
		$this->getTableTracer()->withAllTables( false )->inferTableFrom( $content, $normalize );

		$iterator = $this->getTableTracer()->getTableData()[ $this->getTableTracer()->getTableId( current: true ) ]
			?? ScraperError::withSourceMsg(
				'Table Dataset Iterator not found in class: "%s". Maybe this is used again after reset?',
				static::class
			);

		$this->getTableTracer()->resetTableTraced();

		return $iterator;
	}
}
