<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Fixture\Table;

use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\ScrapeTraceableTable;
use TheWebSolver\Codegarage\Test\Fixture\Table\TableScrapingService;
use TheWebSolver\Codegarage\Scraper\Integration\Cli\TableConsole as CliTableConsole;

class TableConsoleTraitStub {
	/** @use CliTableConsole<string> */
	use CliTableConsole;

	/** @return ScrapeTraceableTable<string,TableTracer<string>> */
	public function scraper(): ScrapeTraceableTable {
		return new TableScrapingService( new StringTableTracer(), null );
	}

	protected function getTableContextForOutput(): string {
		return 'test';
	}

	protected function getInputValue(): array {
		return [
			'indexKey'    => null,
			'accent'      => null,
			'datasetKeys' => null,
			'extension'   => '',
			'filename'    => '',
		];
	}

	protected function defaultCachePath(): string {
		return '';
	}
}
