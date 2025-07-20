<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

/** @template TInferredColumn */
interface ScrapeTraceableTable {
	/**
	 * Gets the tracer to infer table data after content is scraped.
	 *
	 * @return TableTracer<TInferredColumn>
	 */
	public function getTableTracer(): TableTracer;
}
