<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits\Table;

use Closure;
use Iterator;
use ArrayObject;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\ScrapeTraceableTable;

/**
 * @template TTracedItem
 * @template TTracer of TableTracer<TTracedItem>
 */
trait TableDatasetIterator {
	/** Gets the scraper for scraping and caching traced table dataset. */
	abstract protected function scraper(): ScrapeTraceableTable;

	/**
	 * @param array{
	 *  beforeScrape?: (Closure(ScrapeTraceableTable<TTracedItem,TTracer>, static): mixed),
	 *  afterScrape ?: (Closure(string, ScrapeTraceableTable<TTracedItem,TTracer>, static): mixed),
	 *  afterCache  ?: (Closure(ScrapeTraceableTable<TTracedItem,TTracer>, static): mixed)
	 * } $actions Actions are never fired if `$ignoreCache` is `false` & cached file exists.
	 * @param bool   $ignoreCache Whether to verify if scraped content is already cached or not. However, it
	 *                            does not prevent scraped content from being cached if not already cached.
	 * @return Iterator<array-key,ArrayObject<array-key,TTracedItem>>
	 * @throws ScraperError When scraping fails or when caching fails after scraping the content.
	 */
	protected function getIterableDataset( ?array $actions = null, bool $ignoreCache = false ): Iterator {
		$this->scraper()->getTableTracer()->traceWithout( Table::Caption, Table::Head );

		if ( ! $ignoreCache && $this->scraper()->hasCache() ) {
			// TODO: maybe add action if scraped data is already cached.
			return $this->scraper()->parse( $this->scraper()->fromCache() );
		}

		isset( $actions['beforeScrape'] ) && ( $actions['beforeScrape'] )( $this->scraper(), $this );

		$content = $this->scraper()->scrape();
		$url     = $this->scraper()->getSourceUrl();

		isset( $actions['afterScrape'] ) && ( $actions['afterScrape'] )( $url, $this->scraper(), $this );

		$this->scraper()->toCache( $content );

		isset( $actions['afterCache'] ) && ( $actions['afterCache'] )( $this->scraper(), $this );

		return $this->scraper()->parse( $this->scraper()->fromCache() );
	}
}
