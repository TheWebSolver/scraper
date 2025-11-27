<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper;

use Closure;
use Iterator;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Interfaces\Scrapable;

class Factory {
	/**
	 * @param Scrapable<Iterator<array-key,ScrapedDataset>> $scraper
	 * @param null|array{
	 *  beforeScrape?: (Closure(Scrapable<Iterator<array-key,ScrapedDataset>>, static): void),
	 *  afterScrape ?: (Closure(string, Scrapable<Iterator<array-key,ScrapedDataset>>, static): void),
	 *  afterCache  ?: (Closure(Scrapable<Iterator<array-key,ScrapedDataset>>, static): void)
	 * } $actions Actions are never fired if `$ignoreCache` is `false` & cached file exists.
	 * @param bool                                          $ignoreCache Whether to verify if scraped content is already cached or not. However, it
	 *                                                                   does not prevent scraped content from being cached if not already cached.
	 * @return Iterator<array-key,ScrapedDataset>
	 * @throws ScraperError When scraping fails or when caching fails after scraping the content.
	 * @template ScrapedDataset
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
	 * @param Scrapable<Iterator<array-key,ScrapedDataset>> $scraper
	 * @return ?Iterator<array-key,ScrapedDataset>
	 * @template ScrapedDataset
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
