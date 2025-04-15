<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use ArrayObject;

/**
 * @template TColumnReturn
 * @template-extends Scrapable<array-key,ArrayObject<array-key,TColumnReturn>>
 */
interface SingleTableScraperWithAccent extends IndexableTracerWithAccent, Scrapable, KeyMapper {}
