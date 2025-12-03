<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Service;

use Iterator;
use ArrayObject;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Event\TableTraced;
use TheWebSolver\Codegarage\Scraper\Interfaces\Indexable;
use TheWebSolver\Codegarage\Scraper\Interfaces\Traceable;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Validatable;
use TheWebSolver\Codegarage\Scraper\Service\ScrapingService;
use TheWebSolver\Codegarage\Scraper\Proxy\ItemValidatorProxy;
use TheWebSolver\Codegarage\Scraper\Marshaller\MarshallTableRow;
use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedIndexableItem;

/**
 * @template TableColumnValue
 * @template TTracer of TableTracer<TableColumnValue>
 * @template-extends ScrapingService<Iterator<array-key,ArrayObject<array-key,TableColumnValue>>,TTracer>
 */
abstract class TableScrapingService extends ScrapingService {
	/** @param TTracer $tracer */
	public function __construct( Traceable $tracer, ?ScrapeFrom $scrapeFrom = null ) {
		parent::__construct( $tracer->addEventListener( $this->hydrateWithDefaultTransformers( ... ), Table::Row ), $scrapeFrom );
	}

	public function parse(): Iterator {
		( $tracer = $this->getTracer() )->withAllTables( false )->inferFrom( $this->fromCache(), normalize: true );

		yield from $tracer->getData();
	}

	public function flush(): void {
		parent::flush();
		$this->getTracer()->resetTraced();
		$this->getTracer()->resetHooks();
	}

	protected function hydrateWithDefaultTransformers( TableTraced $event ): void {
		$tracer = $event->tracer;

		if (
			! $tracer->hasTransformer( Table::Column )
				&& $tracer instanceof AccentedIndexableItem
				&& $tracer instanceof Validatable
		) {
			$tracer->addTransformer( new ItemValidatorProxy(), Table::Column );
		}

		if ( $tracer->hasTransformer( Table::Row ) ) {
			return;
		}

		$rowTransformer = new MarshallTableRow(
			invalidCountMsg: $this->getScraperSource()->name . ' ' . Indexable::INVALID_COUNT,
			indexKey: $tracer->getIndicesSource()?->indexKey
		);

		$tracer->addTransformer( $rowTransformer, Table::Row );
	}
}
