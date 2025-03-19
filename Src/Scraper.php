<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper;

use Closure;
use TheWebSolver\Codegarage\Scraper\Traits\ScrapeYard;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Interfaces\Scrapable;
use TheWebSolver\Codegarage\Scraper\Traits\ScraperSource;

/**
 * @template TKey
 * @template TValue
 * @template-implements Scrapable<TKey,TValue>
 */
abstract class Scraper implements Scrapable {
	use ScrapeYard, ScraperSource;

	private Closure $unsubscribeError;

	/** @param string $sourceUrl The source URL from which the HTML data should be scraped. */
	public function __construct( string $sourceUrl = '' ) {
		$this->sourceFromAttribute( $sourceUrl )
			->withCachePath( $this->defaultCachePath(), $this->getSource()->filename );

		$this->unsubscribeError = ScraperError::for( $this->getSource() );
	}

	public function flush(): void {
		( $this->unsubscribeError )();
	}
}
