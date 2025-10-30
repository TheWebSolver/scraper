<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use ArrayObject;

/**
 * @template TableColumnValue
 * @template TTracer of TableTracer<TableColumnValue>
 * @template-extends Scrapable<array-key,ArrayObject<array-key,TableColumnValue>>
 */
interface ScrapeTraceableTable extends Scrapable {
	/**
	 * Gets the tracer to infer table data after content is scraped.
	 *
	 * @return TTracer
	 */
	public function getTableTracer(): TableTracer;
}
