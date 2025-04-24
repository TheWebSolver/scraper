<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper;

use Closure;
use ArrayObject;
use ReflectionClass;
use TheWebSolver\Codegarage\Scraper\Traits\ScrapeYard;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Interfaces\Scrapable;
use TheWebSolver\Codegarage\Scraper\Traits\ScraperSource;

/**
 * @template TColumnReturn
 * @template-implements Scrapable<array-key,ArrayObject<array-key,TColumnReturn>>
 */
abstract class ScrapingService implements Scrapable {
	use ScrapeYard, ScraperSource;

	private Closure $unsubscribeError;

	public function __construct() {
		$this->sourceFromAttribute( new ReflectionClass( $this ) )
			->withCachePath( $this->defaultCachePath(), $this->getScraperSource()->filename );

		$this->unsubscribeError = ScraperError::for( $this->getScraperSource() );
	}

	public function flush(): void {
		( $this->unsubscribeError )();
	}
}
