<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Scraper\Traits\ScrapeYard;
use TheWebSolver\Codegarage\Scraper\Interfaces\Scrapable;
use TheWebSolver\Codegarage\Scraper\Traits\ScraperSource;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;

class ScraperTest extends TestCase {
	private Scraper $scraper;

	protected function setUp(): void {
		$this->scraper = new Scraper();
	}

	protected function tearDown(): void {
		unset( $this->scraper );
	}

	#[Test]
	public function itReturnsDefaultGetterValues(): void {
		$this->assertNull( $this->scraper->getDiacritic() );
		$this->assertFalse( $this->scraper->hasCache() );
		$this->assertSame( '', $this->scraper->getCachePath() );
		$this->assertSame( '', $this->scraper->getSourceUrl() );
	}

	#[Test]
	public function itEnsuresDiacriticsAreProperlySet(): void {
		$this->scraper->setDiacritic( Scraper::DIACRITICS_ESCAPE );
		$this->assertSame( Scraper::DIACRITICS_ESCAPE, $this->scraper->getDiacritic() );
	}

	#[Test]
	public function itEnsuresCachePathExistsAndContentIsRetrievable(): void {
		$this->scraper->withCachePath( '', 'partial-content.html' );

		$this->assertTrue( $this->scraper->hasCache() );
		$this->assertSame(
			DOMDocumentFactoryTest::RESOURCE_PATH . 'partial-content.html',
			$this->scraper->getCachePath()
		);
		$this->assertStringContainsString( '<div id="no-html-tag">', $this->scraper->fromCache() );

		$scraper = new #[ScrapeFrom( name: 'Test', url: 'https://php.net', filename: 'cacheFile' )]
		class() extends Scraper {};

		$scraper->sourceFromAttribute()->withCachePath( DOMDocumentFactoryTest::RESOURCE_PATH, 'full-content.html' );

		$this->assertStringContainsString( '<div id="with-html-tag">', $scraper->fromCache() );
		$this->assertSame( 'https://php.net', $scraper->getSourceUrl() );
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound, Squiz.Commenting.ClassComment.WrongStyle

/** @template-implements Scrapable<string,array<string,string>> */
class Scraper implements Scrapable {
	use ScrapeYard, ScraperSource {
		ScraperSource::sourceFromAttribute as public;
	}

	public function parse( string $content ): iterable {
		return array();
	}

	public function invalidateCache(): bool {
		return true;
	}

	public function flush(): void {}

	protected function defaultCachePath(): string {
		return DOMDocumentFactoryTest::RESOURCE_PATH;
	}

	/** @return array<string,string> */
	protected function getDiacritics(): array {
		return array(
			'ä' => 'ae',
			'Ď' => 'd',
			'ė' => 'e',
			'ô' => 'o',
			'ŗ' => 'r',
		);
	}
}
