<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Fixture\Table;

use TheWebSolver\Codegarage\Test\DOMDocumentFactoryTest;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Service\TableScrapingService as AbstractTableScrapingService;

/** @template-extends AbstractTableScrapingService<string,TableTracer<string>> */
#[ScrapeFrom( 'Scraping with Table Tracer', url: 'https://thisIs.test', filename: 'single-table.html' )]
class TableScrapingService extends AbstractTableScrapingService {
	protected function defaultCachePath(): string {
		return DOMDocumentFactoryTest::RESOURCE_PATH;
	}
}
