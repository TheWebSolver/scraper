<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper;

use Closure;
use Iterator;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Interfaces\Scrapable;

/** @template ScrapedDataset */
class Factory {
	/**
	 * @param Scraper     $scraper
	 * @param null|array{
	 *  beforeScrape?: (Closure(Scraper, static): void),
	 *  afterScrape ?: (Closure(string, Scraper, static): void),
	 *  afterCache  ?: (Closure(Scraper, static): void)
	 * } $actions Actions are never fired if `$ignoreCache` is `false` & cached file exists.
	 * @param bool        $ignoreCache Whether to verify if scraped content is already cached or not. However, it
	 *                                 does not prevent scraped content from being cached if not already cached.
	 * @return Iterator<array-key,ScrapedDataset>
	 * @throws ScraperError When scraping fails or when caching fails after scraping the content.
	 * @template Scraper of Scrapable
	 */
	public function generateDataIterator( Scrapable $scraper, ?array $actions = null, bool $ignoreCache = false ): Iterator {
		if ( null !== ( $iterator = $this->fromCacheOrInvalidate( $scraper, $ignoreCache ) ) ) {
			return $iterator;
		}

		isset( $actions['beforeScrape'] ) && ( $actions['beforeScrape'] )( $scraper, $this );

		$content = $scraper->scrape();
		$url     = $scraper->getSourceUrl();

		isset( $actions['afterScrape'] ) && ( $actions['afterScrape'] )( $url, $scraper, $this );

		$scraper->toCache( $content );

		isset( $actions['afterCache'] ) && ( $actions['afterCache'] )( $scraper, $this );

		return $scraper->parse();
	}

	/**
	 * @param Scraper $scraper
	 * @return ?Iterator<array-key,ScrapedDataset>
	 * @template Scraper of Scrapable
	 */
	private function fromCacheOrInvalidate( Scrapable $scraper, bool $ignoreCache ): ?Iterator {
		if ( ! $ignoreCache ) {
			if ( $scraper->hasCache() ) {
				// TODO: maybe add action if scraped data is already cached.
				return $scraper->parse();
			}
		} else {
			$scraper->invalidateCache();
		}

		return null;
	}
}
