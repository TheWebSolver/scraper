<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Service;

use Iterator;
use ArrayObject;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\ScrapeTraceableTable;

/**
 * @template TInferredColumn
 * @template-extends ScrapingService<TInferredColumn>
 * @template-implements ScrapeTraceableTable<TInferredColumn>
 */
abstract class TableScrapingService extends ScrapingService implements ScrapeTraceableTable {
	/** @param TableTracer<TInferredColumn> $tracer */
	public function __construct( protected TableTracer $tracer ) {
		$tracer->withAllTables( false );

		parent::__construct();
	}

	public function getTableTracer(): TableTracer {
		return $this->tracer;
	}

	public function flush(): void {
		parent::flush();
		$this->getTableTracer()->resetTableHooks();
	}

	/** @return Iterator<array-key,ArrayObject<array-key,TInferredColumn>> */
	protected function currentTableIterator( string $content, bool $normalize = true ): Iterator {
		$this->tracer->inferTableFrom( $content, $normalize );

		$iterator = $this->tracer->getTableData()[ $this->tracer->getTableId( current: true ) ]
			?? ScraperError::withSourceMsg(
				'Table Dataset Iterator not found in class: "%s". Maybe this is used again after reset?',
				static::class
			);

		$this->tracer->resetTableTraced();

		return $iterator;
	}
}
