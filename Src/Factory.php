<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper;

use Iterator;
use ArrayObject;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\ScrapeTraceableTable;
use TheWebSolver\Codegarage\Scraper\Traits\Table\TableDatasetIterator;

class Factory {
	/** @use TableDatasetIterator<mixed,TableTracer<mixed>> */
	use TableDatasetIterator;

	/** @param ScrapeTraceableTable<mixed,TableTracer<mixed>> $scraper */
	public function __construct( private readonly ScrapeTraceableTable $scraper ) {}

	/** @return Iterator<array-key,ArrayObject<array-key,mixed>> */
	public function generate( bool $force = false ): Iterator {
		$force && $this->scraper()->hasCache() && $this->scraper()->invalidateCache();

		return $this->getIterableDataset();
	}

	/** @return ScrapeTraceableTable<mixed,TableTracer<mixed>> */
	protected function scraper(): ScrapeTraceableTable {
		return $this->scraper;
	}
}
