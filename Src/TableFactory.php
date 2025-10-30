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
 * @template TableColumnValue
 * @template TTracer of TableTracer<TableColumnValue>
 */
abstract class TableFactory {
	/** @return ScrapeTraceableTable<TableColumnValue,covariant TTracer> */
	abstract public function scraper(): ScrapeTraceableTable;

	/**
	 * @param array{
	 *  beforeScrape?: (Closure(ScrapeTraceableTable<TableColumnValue,covariant TTracer>, static): void),
	 *  afterScrape ?: (Closure(string, ScrapeTraceableTable<TableColumnValue,covariant TTracer>, static): void),
	 *  afterCache  ?: (Closure(ScrapeTraceableTable<TableColumnValue,covariant TTracer>, static): void)
	 * } $actions Actions are never fired if `$ignoreCache` is `false` & cached file exists.
	 * @param bool   $ignoreCache Whether to verify if scraped content is already cached or not. However, it
	 *                            does not prevent scraped content from being cached if not already cached.
	 * @return Iterator<array-key,ArrayObject<array-key,TableColumnValue>>
	 * @throws ScraperError When scraping fails or when caching fails after scraping the content.
	 */
	public function generateRowIterator( ?array $actions = null, bool $ignoreCache = false ): Iterator {
		$scraper = $this->scraper();

		if ( null !== ( $iterator = $this->fromCacheOrInvalidate( $scraper, $ignoreCache ) ) ) {
			return $iterator;
		}

		isset( $actions['beforeScrape'] ) && ( $actions['beforeScrape'] )( $scraper, $this );

		$content = $scraper->scrape();
		$url     = $scraper->getSourceUrl();

		isset( $actions['afterScrape'] ) && ( $actions['afterScrape'] )( $url, $scraper, $this );

		$scraper->toCache( $content );

		isset( $actions['afterCache'] ) && ( $actions['afterCache'] )( $scraper, $this );

		return $scraper->parse( $scraper->fromCache() );
	}

	/**
	 * @param ScrapeTraceableTable<TableColumnValue,covariant TTracer> $scraper
	 * @return ?Iterator<array-key,ArrayObject<array-key,TableColumnValue>>
	 */
	private function fromCacheOrInvalidate( ScrapeTraceableTable $scraper, bool $ignoreCache ): ?Iterator {
		if ( ! $ignoreCache ) {
			if ( $scraper->hasCache() ) {
				// TODO: maybe add action if scraped data is already cached.
				return $scraper->parse( $scraper->fromCache() );
			}
		} else {
			$scraper->invalidateCache();
		}

		return null;
	}
}
