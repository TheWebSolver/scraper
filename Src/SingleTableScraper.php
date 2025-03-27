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
use TheWebSolver\Codegarage\Scraper\Traits\CollectionAware;

/**
 * @template TdReturn
 * @template-implements Scrapable<array-key,ArrayObject<array-key,TdReturn>>
 * @template-implements TableTracer<string,TdReturn>
 */
abstract class SingleTableScraper implements Scrapable, TableTracer {
	/** @use TableNodeAware<string,TdReturn> */
	use ScrapeYard, ScraperSource, TableNodeAware, CollectionAware;

	private Closure $unsubscribeError;

	public function __construct() {
		$reflection = new ReflectionClass( $this );

		$this->sourceFromAttribute( $reflection )
			->collectableFromAttribute( $reflection )
			->subscribeWith( $this->beforeTableTraceListener( ... ) )
			->withCachePath( $this->defaultCachePath(), $this->getScraperSource()->filename );

		$this->unsubscribeError = ScraperError::for( $this->getScraperSource() );
	}

	public function flush(): void {
		( $this->unsubscribeError )();
		$this->flushTransformers();
	}

	/** @return Iterator<string|int,ArrayObject<array-key,TdReturn>> */
	protected function validateCurrentTableParsedData( string $content ): Iterator {
		! empty( $this->getKeys() ) || $this->useKeys( $this->getCollectionSource()->items );

		$this->traceTableIn( DOMDocumentFactory::createFromHtml( $content )->childNodes );

		$data = $this->getTableData()[ $this->getTableId( current: true ) ]
			?? ScraperError::withSourceMsg( 'Could not find parsable content.' );

		$this->flushDiscoveredContents();

		return $data;
	}

	private function beforeTableTraceListener(): void {
		$this->withAllTables( false )
			->setColumnNames( $this->getCollectionSource()->items, $this->getTableId( current: true ) );
	}
}
