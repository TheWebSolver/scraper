<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Scraper\Traits\ScrapeYard;
use TheWebSolver\Codegarage\Scraper\Interfaces\Scrapable;
use TheWebSolver\Codegarage\Scraper\Traits\ScraperSource;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;
use TheWebSolver\Codegarage\Scraper\Interfaces\ScraperEnum;

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
		$this->assertSame( array(), $this->scraper->getKeys() );
		$this->assertNull( $this->scraper->getIndexKey() );
		$this->assertSame( '', $this->scraper->getCachePath() );
		$this->assertSame( '', $this->scraper->getSourceUrl() );
	}

	#[Test]
	public function itEnsuresDiacriticsAreProperlySet(): void {
		$this->scraper->setDiacritic( Scraper::DIACRITICS_ESCAPE );
		$this->assertSame( Scraper::DIACRITICS_ESCAPE, $this->scraper->getDiacritic() );
	}

	/**
	 * @param array{0:string[],1:?string} $expected
	 * @param string[]                    $keys
	 */
	#[Test]
	#[DataProvider( 'provideScraperKeys' )]
	public function itSetsScrapedDataCollectSetKeysAndIndexKeyBasedOnArgPassed(
		array $expected,
		array $keys,
		?string $key = null,
	): void {
		[$expectedKeys, $expectedKey] = $expected;

		$this->scraper->useKeys( $keys, $key );
		$this->assertSame( $expectedKeys, $this->scraper->getKeys() );
		$this->assertSame( $expectedKey, $this->scraper->getIndexKey() );
	}

	/** @return mixed[] */
	public static function provideScraperKeys(): array {
		return array(
			array( array( array(), null ), array() ),
			array( array( array( 'name', 'title', 'address' ), null ), array( 'name', 'title', 'address' ) ),
			array( array( array( 'name', 'title', 'address' ), 'title' ), array( 'name', 'title', 'address' ), 'title' ),
		);
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

		$scraper = new #[ScrapeFrom( url: 'https://php.net', filename: 'cacheFile', scraperEnum: ScraperEnumStub::class, name: 'Test' )]
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

enum ScraperEnumStub: string implements ScraperEnum {
	case Test = 'test';

	public function length(): int {
		return 0;
	}

	public function errorMsg(): string {
		return '';
	}

	public static function type(): string {
		return '';
	}

	public function isCharacterTypeAndLength( string $value ): bool {
		return ! ! $value;
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	public static function toArray( \BackedEnum ...$filter ): array {
		return array();
	}

	public static function invalidCountMsg(): string {
		return '';
	}

	public static function walkForTypeVerification( string $data, string $key, \Closure $handler ): bool {
		return true;
	}
}
