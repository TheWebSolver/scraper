<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

/**
 * @template TTracedType
 * @template TInferredType
 * @template-extends TableTracer<string,TTracedType>
 * @template-extends Scrapable<array-key,TInferredType>
 */
interface MappableTableScraper extends Scrapable, KeyMapper, TableTracer {}
