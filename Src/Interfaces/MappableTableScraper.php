<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

/**
 * @template TTracedValue
 * @template TInferredValue
 * @template-extends TableTracer<TTracedValue>
 * @template-extends Scrapable<array-key,TInferredValue>
 */
interface MappableTableScraper extends Scrapable, KeyMapper, TableTracer {}
