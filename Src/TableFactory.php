<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper;

use Closure;
use Iterator;
use ArrayObject;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\ScrapeTraceableTable;

/**
 * @template TRowItems
 * @template TTracer of TableTracer<TRowItems>
 */
abstract class TableFactory {
	/** @return ScrapeTraceableTable<TRowItems,covariant TTracer> */
	abstract public function scraper(): ScrapeTraceableTable;

	/**
	 * @param array{
	 *  beforeScrape?: (Closure(ScrapeTraceableTable<TRowItems,covariant TTracer>, static): void),
	 *  afterScrape ?: (Closure(string, ScrapeTraceableTable<TRowItems,covariant TTracer>, static): void),
	 *  afterCache  ?: (Closure(ScrapeTraceableTable<TRowItems,covariant TTracer>, static): void)
	 * } $actions Actions are never fired if `$ignoreCache` is `false` & cached file exists.
	 * @param bool   $ignoreCache Whether to verify if scraped content is already cached or not. However, it
	 *                            does not prevent scraped content from being cached if not already cached.
	 * @return Iterator<array-key,ArrayObject<array-key,TRowItems>>
	 * @throws ScraperError When scraping fails or when caching fails after scraping the content.
	 */
	public function generateRowIterator( ?array $actions = null, bool $ignoreCache = false ): Iterator {
		if ( null !== ( $iterator = $this->fromCacheOrInvalidate( $ignoreCache ) ) ) {
			return $iterator;
		}

		isset( $actions['beforeScrape'] ) && ( $actions['beforeScrape'] )( $this->scraper(), $this );

		$content = $this->scraper()->scrape();
		$url     = $this->scraper()->getSourceUrl();

		isset( $actions['afterScrape'] ) && ( $actions['afterScrape'] )( $url, $this->scraper(), $this );

		$this->scraper()->toCache( $content );

		isset( $actions['afterCache'] ) && ( $actions['afterCache'] )( $this->scraper(), $this );

		return $this->scraper()->parse( $this->scraper()->fromCache() );
	}

	/** @return ?Iterator<array-key,ArrayObject<array-key,TRowItems>> */
	private function fromCacheOrInvalidate( bool $ignoreCache ): ?Iterator {
		if ( ! $ignoreCache ) {
			if ( $this->scraper()->hasCache() ) {
				// TODO: maybe add action if scraped data is already cached.
				return $this->scraper()->parse( $this->scraper()->fromCache() );
			}
		} else {
			$this->scraper()->invalidateCache();
		}

		return null;
	}
}
