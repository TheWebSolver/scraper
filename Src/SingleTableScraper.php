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
use TheWebSolver\Codegarage\Scraper\Traits\ScraperSource;
use TheWebSolver\Codegarage\Scraper\Traits\CollectorSource;
use TheWebSolver\Codegarage\Scraper\Traits\HtmlTableFromNode;
use TheWebSolver\Codegarage\Scraper\Interfaces\MappableTableScraper;

/**
 * @template TdReturn
 * @template-implements MappableTableScraper<TdReturn,ArrayObject<array-key,TdReturn>>
 */
abstract class SingleTableScraper implements MappableTableScraper {
	/** @use HtmlTableFromNode<string,TdReturn> */
	use ScrapeYard, HtmlTableFromNode, ScraperSource, CollectorSource;

	private Closure $unsubscribeError;

	public function __construct() {
		$reflection = new ReflectionClass( $this );

		$this->sourceFromAttribute( $reflection )
			->collectableFromAttribute( $reflection )
			->addEventListener( Table::TBody, $this->tableBodyListener( ... ) )
			->withCachePath( $this->defaultCachePath(), $this->getScraperSource()->filename )
			->useKeys( $this->getCollectionSource()->items ?? array() );

		$this->unsubscribeError = ScraperError::for( $this->getScraperSource() );
	}

	public function flush(): void {
		( $this->unsubscribeError )();
		$this->flushDiscoveredTableHooks();
		$this->flushDiscoveredTableStructure();
	}

	/** @return Iterator<string|int,ArrayObject<array-key,TdReturn>> */
	protected function currentTableIterator( string $content, bool $normalize = true ): Iterator {
		$this->withAllTables( false );

		empty( $this->getKeys() ) && $this->useKeys( $this->getCollectionSource()->items ?? array() );

		$this->inferTableFromDOMNodeList(
			DOMDocumentFactory::bodyFromHtml( $content, normalize: $normalize )->childNodes
		);

		$iterator = $this->getTableData()[ $this->getTableId( current: true ) ]
			?? ScraperError::withSourceMsg( 'Could not find Iterator that generates Table Dataset.' );

		$this->flushDiscoveredTableStructure();

		return $iterator;
	}

	protected function tableBodyListener(): void {
		( $source = $this->getCollectionSource() )
			&& $this->setColumnNames( $source->items, $this->getTableId( current: true ) );
	}
}
