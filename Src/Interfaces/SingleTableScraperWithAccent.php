<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use ArrayObject;

/**
 * @template TValue
 * @template-extends MappableTableScraper<TValue,ArrayObject<array-key,TValue>>
 */
interface SingleTableScraperWithAccent extends MappableTableScraper, AccentedCharacter {}
