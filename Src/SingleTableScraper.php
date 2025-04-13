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
use TheWebSolver\Codegarage\Scraper\Interfaces\MappableTableScraper;

/**
 * @template TColumnReturn
 * @template-implements MappableTableScraper<TColumnReturn>
 */
abstract class SingleTableScraper implements MappableTableScraper {
	use ScrapeYard, ScraperSource, CollectorSource;

	private Closure $unsubscribeError;

	public function __construct() {
		$reflection = new ReflectionClass( $this );

		$this->sourceFromAttribute( $reflection )
			->collectableFromAttribute( $reflection )
			->withCachePath( $this->defaultCachePath(), $this->getScraperSource()->filename )
			->useCollectedKeys();

		$this->getCollectionSource() && $this->addEventListener( Table::TBody, $this->tableBodyListener( ... ) );

		$this->unsubscribeError = ScraperError::for( $this->getScraperSource() );
	}

	/** @return list<string> */
	final protected function useCollectedKeys(): array {
		empty( $this->getKeys() ) && $this->useKeys( $this->getCollectionSource()->items ?? array() );

		return $this->getKeys();
	}

	public function flush(): void {
		( $this->unsubscribeError )();
		$this->resetTableTraced();
		$this->resetTableHooks();
	}

	/** @return Iterator<array-key,ArrayObject<array-key,TColumnReturn>> */
	protected function currentTableIterator( string $content, bool $normalize = true ): Iterator {
		$this->withAllTables( false );
		$this->useCollectedKeys();
		$this->inferTableFrom( $content, $normalize );

		$iterator = $this->getTableData()[ $this->getTableId( current: true ) ]
			?? ScraperError::withSourceMsg( 'Could not find Iterator that generates Table Dataset.' );

		$this->resetTableTraced();

		return $iterator;
	}

	/**
	 * Inheriting class may override this method to provide column names with offset position(s).
	 * By default, it is only invoked if collection source exists. Hence, source is never null.
	 */
	protected function tableBodyListener(): void {
		$this->setColumnNames( $this->getCollectionSource()->items, $this->getTableId( current: true ) );
	}
}
