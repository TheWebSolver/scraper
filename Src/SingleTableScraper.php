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

/**
 * @template TColumnReturn
 * @template-implements TableTracer<TColumnReturn>
 * @template-implements Scrapable<array-key,ArrayObject<array-key,TColumnReturn>>
 */
abstract class SingleTableScraper implements TableTracer, Scrapable {
	use ScrapeYard, ScraperSource, CollectorSource;

	private Closure $unsubscribeError;

	public function __construct() {
		$reflection = new ReflectionClass( $this );

		$this->sourceFromAttribute( $reflection )
			->collectableFromAttribute( $reflection )
			->withCachePath( $this->defaultCachePath(), $this->getScraperSource()->filename )
			->collectSourceItems();

		$this->getCollectionSource()
			&& $this->addEventListener( Table::Row, $this->useCollectedKeysAsTableColumnIndices( ... ) );

		$this->unsubscribeError = ScraperError::for( $this->getScraperSource() );
	}

	public function flush(): void {
		( $this->unsubscribeError )();
		$this->resetTableTraced();
		$this->resetTableHooks();
	}

	/** @return Iterator<array-key,ArrayObject<array-key,TColumnReturn>> */
	protected function currentTableIterator( string $content, bool $normalize = true ): Iterator {
		$this->withAllTables( false );
		$this->collectSourceItems();
		$this->inferTableFrom( $content, $normalize );

		$iterator = $this->getTableData()[ $this->getTableId( current: true ) ]
			?? ScraperError::withSourceMsg( 'Could not find Iterator that generates Table Dataset.' );

		$this->resetTableTraced();

		return $iterator;
	}

	/**
	 * Inheriting class may override this method to provide column names with offset position(s).
	 * Use `$this->collectSourceItems()` as indices and offset position(s) as required.
	 */
	protected function useCollectedKeysAsTableColumnIndices(): void {
		$this->setItemsIndices( $this->collectSourceItems() );
	}
}
