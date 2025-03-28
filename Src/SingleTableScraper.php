<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper;

use Closure;
use Iterator;
use ArrayObject;
use ReflectionClass;
use TheWebSolver\Codegarage\Scraper\Traits\ScrapeYard;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Interfaces\Scrapable;
use TheWebSolver\Codegarage\Scraper\Traits\ScraperSource;
use TheWebSolver\Codegarage\Scraper\Traits\TableNodeAware;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Traits\CollectorSource;

/**
 * @template TdReturn
 * @template-implements Scrapable<array-key,ArrayObject<array-key,TdReturn>>
 * @template-implements TableTracer<string,TdReturn>
 */
abstract class SingleTableScraper implements Scrapable, TableTracer {
	/** @use TableNodeAware<string,TdReturn> */
	use ScrapeYard, ScraperSource, TableNodeAware, CollectorSource;

	private Closure $unsubscribeError;

	public function __construct() {
		$reflection = new ReflectionClass( $this );

		$this->sourceFromAttribute( $reflection )
			->collectableFromAttribute( $reflection )
			->subscribeWith( $this->beforeTableTraceListener( ... ) )
			->withCachePath( $this->defaultCachePath(), $this->getScraperSource()->filename );

		( $source = $this->getCollectionSource() ) && $this->useKeys( $source->items );

		$this->unsubscribeError = ScraperError::for( $this->getScraperSource() );
	}

	public function flush(): void {
		( $this->unsubscribeError )();
		$this->flushTransformers();
	}

	/** @return Iterator<string|int,ArrayObject<array-key,TdReturn>> */
	protected function currentTableIterator( string $content, bool $normalize = true ): Iterator {
		$this->withAllTables( false );

		empty( $this->getKeys() )
			&& ( $source = $this->getCollectionSource() )
			&& $this->useKeys( $source->items );

		$this->traceTableIn( DOMDocumentFactory::bodyFromHtml( $content, normalize: $normalize )->childNodes );

		$data = $this->getTableData()[ $this->getTableId( current: true ) ]
			?? ScraperError::withSourceMsg( 'Could not find parsable content.' );

		$this->flushDiscoveredContents();

		return $data;
	}

	private function beforeTableTraceListener(): void {
		( $source = $this->getCollectionSource() )
			&& $this->setColumnNames( $source->items, $this->getTableId( current: true ) );
	}
}
