<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

/**
 * @template TColumnReturn
 * @template-extends MappableTableScraper<TColumnReturn>
 */
interface SingleTableScraperWithAccent extends MappableTableScraper, AccentedCharacter {}
