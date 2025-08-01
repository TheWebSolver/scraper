<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Fixture\Table;

use Iterator;
use TheWebSolver\Codegarage\Test\DOMDocumentFactoryTest;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;
use TheWebSolver\Codegarage\Scraper\Service\TableScrapingService as AbstractTableScrapingService;

/** @template-extends AbstractTableScrapingService<string> */
#[ScrapeFrom( 'Scraping with Table Tracer', url: 'https://thisIs.test', filename: '' )]
class TableScrapingService extends AbstractTableScrapingService {
	public function parse( string $content ): Iterator {
		yield from $this->currentTableIterator( $content );
	}

	protected function defaultCachePath(): string {
		return DOMDocumentFactoryTest::RESOURCE_PATH;
	}
}
