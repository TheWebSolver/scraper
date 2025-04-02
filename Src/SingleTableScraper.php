<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper;

use Closure;
use Iterator;
use ArrayObject;
use ReflectionClass;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Traits\ScrapeYard;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Interfaces\Scrapable;
use TheWebSolver\Codegarage\Scraper\Traits\ScraperSource;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Traits\CollectorSource;
use TheWebSolver\Codegarage\Scraper\Traits\HtmlTableFromNode;

/**
 * @template TdReturn
 * @template-implements Scrapable<array-key,ArrayObject<array-key,TdReturn>>
 * @template-implements TableTracer<string,TdReturn>
 */
abstract class SingleTableScraper implements Scrapable, TableTracer {
	/** @use HtmlTableFromNode<string,TdReturn> */
	use ScrapeYard, ScraperSource, HtmlTableFromNode, CollectorSource;

	private Closure $unsubscribeError;

	public function __construct() {
		$reflection = new ReflectionClass( $this );

		$this->sourceFromAttribute( $reflection )
			->collectableFromAttribute( $reflection )
			->subscribeWith( $this->tableBodyListener( ... ), target: Table::TBody )
			->withCachePath( $this->defaultCachePath(), $this->getScraperSource()->filename )
			->useKeys( $this->getCollectionSource()->items ?? array() );

		$this->unsubscribeError = ScraperError::for( $this->getScraperSource() );
	}

	public function flush(): void {
		( $this->unsubscribeError )();
		$this->flushDiscoveredTableHooks();
	}

	/** @return Iterator<string|int,ArrayObject<array-key,TdReturn>> */
	protected function currentTableIterator( string $content, bool $normalize = true ): Iterator {
		$this->withAllTables( false );

		empty( $this->getKeys() ) && $this->useKeys( $this->getCollectionSource()->items ?? array() );

		$this->inferTableFrom( DOMDocumentFactory::bodyFromHtml( $content, normalize: $normalize )->childNodes );

		$iterator = $this->getTableData()[ $this->getTableId( current: true ) ]
			?? ScraperError::withSourceMsg( 'Could not find table column set iterator.' );

		$this->flushDiscoveredTableStructure();

		return $iterator;
	}

	protected function tableBodyListener(): void {
		( $source = $this->getCollectionSource() )
			&& $this->setColumnNames( $source->items, $this->getTableId( current: true ) );
	}
}
