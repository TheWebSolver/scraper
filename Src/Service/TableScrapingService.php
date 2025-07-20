<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Service;

use Iterator;
use ArrayObject;
use TheWebSolver\Codegarage\Scraper\ScrapingService;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;

/**
 * @template TScrapedColumn
 * @template TTracer of TableTracer<TScrapedColumn>
 * @template-extends ScrapingService<TScrapedColumn>
 */
abstract class TableScrapingService extends ScrapingService {
	/** @param TTracer $tracer */
	public function __construct( protected TableTracer $tracer ) {
		$tracer->withAllTables( false );

		parent::__construct();
	}

	/** @return TTracer */
	public function getTableTracer(): TableTracer {
		return $this->tracer;
	}

	public function parse( string $content ): Iterator {
		yield from $this->currentTableIterator( $content );
	}

	/** @return Iterator<array-key,ArrayObject<array-key,TScrapedColumn>> */
	protected function currentTableIterator( string $content, bool $normalize = true ): Iterator {
		$this->tracer->inferTableFrom( $content, $normalize );

		$iterator = $this->tracer->getTableData()[ $this->tracer->getTableId( current: true ) ]
			?? ScraperError::withSourceMsg(
				'Table Dataset Iterator not found in class: "%s". Maybe this is used again after reset?',
				static::class
			);

		$this->tracer->resetTableTraced();

		return $iterator;
	}
}
