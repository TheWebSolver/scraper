<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use Iterator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Scraper\ScrapingService;
use TheWebSolver\Codegarage\Scraper\Interfaces\Scrapable;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;

class ScrapingServiceTest extends TestCase {
	final public const RESOURCE_PATH = __DIR__ . DIRECTORY_SEPARATOR . 'Resource' . DIRECTORY_SEPARATOR;

	/** @var Scrapable<array-key,mixed> */
	private Scrapable $service;

	protected function setUp(): void {
		$iterator = $this->createStub( Iterator::class );

		// @phpstan-ignore-next-line
		$this->service = new #[ScrapeFrom( 'scrape service', 'https://scrapeService.test', 'full-content.html' )] class( $iterator )
		extends ScrapingService {
			public function __construct( private Iterator $iterator ) {
				parent::__construct();
			}

			public function parse( string $content ): Iterator {
				return $this->iterator;
			}

			protected function defaultCachePath(): string {
				return ScrapingServiceTest::RESOURCE_PATH;
			}
		};
	}

	protected function tearDown(): void {
		unset( $this->service );
	}

	#[Test]
	public function itReturnsDefaultValuesUsingGetters(): void {
		$this->assertNotEmpty( $this->service->fromCache() );
		$this->assertSame( self::RESOURCE_PATH . 'full-content.html', $this->service->getCachePath() );
		$this->assertSame( 'https://scrapeService.test', $this->service->getSourceUrl() );
		$this->assertTrue( $this->service->hasCache() );
	}
}
