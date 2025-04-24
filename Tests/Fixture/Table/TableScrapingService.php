<?php // phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Fixture\Table;

use Iterator;
use TheWebSolver\Codegarage\Scraper\ScrapingService;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;

/**
 * @template TTracer of TableTracer<string>
 * @template-extends ScrapingService<string>
 */
#[ScrapeFrom( 'Scraping with String Table Tracer', url: 'https://thisIs.test', filename: '' )]
class TableScrapingService extends ScrapingService {
	/** @use TraceATable<TTracer> */
	use TraceATable;

	final public const CACHE_PATH = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Resource';

	/** @param TTracer $tracer */
	public function __construct( protected TableTracer $tracer ) {
		parent::__construct();
	}

	public function parse( string $content ): Iterator {
		yield from $this->currentTableIterator( $content );
	}

	protected function defaultCachePath(): string {
		return self::CACHE_PATH;
	}
}
