<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use ArrayObject;

/**
 * @template TInferredColumn
 * @template TTracer of TableTracer<TInferredColumn>
 * @template-extends Scrapable<array-key,ArrayObject<array-key,TInferredColumn>>
 */
interface ScrapeTraceableTable extends Scrapable {
	/**
	 * Gets the tracer to infer table data after content is scraped.
	 *
	 * @return TTracer
	 */
	public function getTableTracer(): TableTracer;
}
