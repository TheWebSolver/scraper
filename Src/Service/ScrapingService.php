<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Service;

use Closure;
use Iterator;
use TheWebSolver\Codegarage\Scraper\Traits\ScrapeYard;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Interfaces\Scrapable;
use TheWebSolver\Codegarage\Scraper\Interfaces\Traceable;
use TheWebSolver\Codegarage\Scraper\Traits\ScraperSource;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;

/**
 * @template ScrapedKeyValue of Iterator
 * @template TTracer of Traceable
 * @template-implements Scrapable<ScrapedKeyValue,TTracer>
 */
abstract class ScrapingService implements Scrapable {
	use ScrapeYard, ScraperSource;

	private Closure $unsubscribeError;

	/** @param TTracer $tracer */
	public function __construct( private Traceable $tracer, ?ScrapeFrom $scrapeFrom = null ) {
		$scrapeFrom && $this->setScraperSource( $scrapeFrom );

		$this->sourceFromAttribute()
			->withCachePath( $this->defaultCachePath(), $this->getScraperSource()->filename );

		$this->unsubscribeError = ScraperError::for( $this->getScraperSource() );
	}

	public function getTracer(): Traceable {
		return $this->tracer;
	}

	public function flush(): void {
		( $this->unsubscribeError )();
	}
}
