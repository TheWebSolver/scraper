<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Service;

use Iterator;
use ArrayObject;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Event\TableTraced;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Interfaces\Indexable;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Validatable;
use TheWebSolver\Codegarage\Scraper\Service\ScrapingService;
use TheWebSolver\Codegarage\Scraper\Proxy\ItemValidatorProxy;
use TheWebSolver\Codegarage\Scraper\Marshaller\MarshallTableRow;
use TheWebSolver\Codegarage\Scraper\Interfaces\ScrapeTraceableTable;
use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedIndexableItem;

/**
 * @template TableColumnValue
 * @template TTracer of TableTracer<TableColumnValue>
 * @template-extends ScrapingService<TableColumnValue>
 * @template-implements ScrapeTraceableTable<TableColumnValue,TTracer>
 */
abstract class TableScrapingService extends ScrapingService implements ScrapeTraceableTable {
	/** @param TTracer $tracer */
	public function __construct( protected TableTracer $tracer, ?ScrapeFrom $scrapeFrom = null ) {
		$scrapeFrom && $this->setScraperSource( $scrapeFrom );

		$tracer->withAllTables( false );

		parent::__construct();
	}

	public function getTableTracer(): TableTracer {
		return $this->tracer;
	}

	public function parse( string $content ): Iterator {
		$this->getTableTracer()->addEventListener( Table::Row, $this->hydrateWithDefaultTransformers( ... ) );

		yield from $this->currentTableIterator( $content );
	}

	public function flush(): void {
		parent::flush();
		$this->getTableTracer()->resetTableTraced();
		$this->getTableTracer()->resetTableHooks();
	}

	/** @return Iterator<array-key,ArrayObject<array-key,TableColumnValue>> */
	protected function currentTableIterator( string $content, bool $normalize = true ): Iterator {
		$this->tracer->inferTableFrom( $content, $normalize );

		return $this->tracer->getTableData()[ $this->tracer->getTableId( current: true ) ]
			?? ScraperError::withSourceMsg(
				'Table Dataset Iterator not found in class: "%s". Maybe this is used again after reset?',
				static::class
			);
	}

	protected function hydrateWithDefaultTransformers( TableTraced $event ): void {
		$tracer = $event->tracer;

		if (
			! $tracer->hasTransformer( Table::Column )
				&& $tracer instanceof AccentedIndexableItem
				&& $tracer instanceof Validatable
		) {
			$tracer->addTransformer( Table::Column, new ItemValidatorProxy() );
		}

		if ( $tracer->hasTransformer( Table::Row ) ) {
			return;
		}

		$rowTransformer = new MarshallTableRow(
			invalidCountMsg: $this->getScraperSource()->name . ' ' . Indexable::INVALID_COUNT,
			indexKey: $tracer->getIndicesSource()?->indexKey
		);

		$tracer->addTransformer( Table::Row, $rowTransformer );
	}
}
