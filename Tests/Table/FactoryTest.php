<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use Exception;
use ArrayObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TheWebSolver\Codegarage\Scraper\Factory;
use TheWebSolver\Codegarage\Scraper\Enums\AccentedChars;
use TheWebSolver\Codegarage\Scraper\Interfaces\Scrapable;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Test\Fixture\Table\NodeTableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedCharacter;
use TheWebSolver\Codegarage\Test\Fixture\Table\StringTableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\ScrapeTraceableTable;
use TheWebSolver\Codegarage\Test\Fixture\Table\TableScrapingService;

class FactoryTest extends TestCase {
	#[Test]
	public function itTracesTableDataFromCache(): void {
		foreach ( [ StringTableTracer::class, NodeTableTracer::class ] as $tracer ) {
			$scraper     = new #[ScrapeFrom( 'cache', 'file', 'single-table.html' )] class( new $tracer() ) extends TableScrapingService {};
			$iterator    = ( new Factory() )->generateDataIterator( $scraper );
			$johnJob     = StringTableTracer::class === $tracer ? 'PHP Devel&ocirc;per' : 'PHP Develôper';
			$johnAddress = StringTableTracer::class === $tracer
				? '<a href="/location" title="Developer location">Ktm</a>'
				: 'Ktm';

			$this->assertSame(
				[ 'John Doe', $johnJob, $johnAddress, '22' ],
				$iterator->current()->getArrayCopy(),
				$tracer
			);

			$iterator->next();

			$this->assertSame(
				[ 'Lorem Ipsum', 'JS Developer', 'Bkt','19' ],
				$iterator->current()->getArrayCopy(),
				$tracer
			);

			$iterator->next();

			$this->assertFalse( $iterator->valid() );
		}//end foreach
	}

	#[Test]
	public function itMocksActionsPerformedByGenerateDataIterator(): void {
		/** @var MockObject&ScrapeTraceableTable<string,TableTracer<string>> */
		$serviceMock = $this->createMockForIntersectionOfInterfaces( [ ScrapeTraceableTable::class, AccentedCharacter::class ] );
		$file        = DOMDocumentFactoryTest::RESOURCE_PATH . 'single-table.html';
		$factory     = new /** @template-extends Factory<ArrayObject<array-key,string>> */ class() extends Factory {};

		// Called twice:
		// 1: when ignoreCache is false,
		// 2: with $actions['beforeScrape'] passed to $scraperWithActions.
		$serviceMock->expects( $this->exactly( 2 ) )
			->method( 'hasCache' )
			->willReturn( true, false );

		$serviceMock->expects( $this->once() )
			->method( 'scrape' )
			->willReturn( $content = file_get_contents( $file ) );

		// Verify return value with $actions['afterScrape'] passed to $scraperWithActions.
		$serviceMock->expects( $this->once() )
			->method( 'getSourceUrl' )
			->willReturn( 'source.url' );

		$serviceMock->expects( $this->once() )
			->method( 'toCache' )
			->with( $content )
			->willReturn( filesize( $file ) );

		// Verify return value with $actions['afterCache'] passed to $scraperWithActions.
		$serviceMock->expects( $this->once() )
			->method( 'getAccentOperationType' )
			->willReturn( AccentedChars::Translit );

		$factory->generateDataIterator(
			scraper: $serviceMock,
			actions: [ 'beforeScrape' => static fn() => throw new Exception( 'must never be thrown' ) ]
		);

		$scraperWithActions = ( new $factory( $serviceMock ) );
		$actions            = [
			'beforeScrape' => static function ( $service, $factory ) use ( $scraperWithActions ) {
				self::assertInstanceOf( Scrapable::class, $service );
				self::assertFalse( $service->hasCache() );
				self::assertSame( $scraperWithActions, $factory );
			},
			'afterScrape' => static function ( $sourceUrl, $service, $factory ) use ( $serviceMock, $scraperWithActions ) {
				self::assertSame( 'source.url', $sourceUrl );
				self::assertSame( $serviceMock, $service );
				self::assertSame( $scraperWithActions, $factory );
			},
			'afterCache' => static function ( $service, $factory ) use ( $scraperWithActions ) {
				self::assertInstanceOf( AccentedCharacter::class, $service );
				self::assertSame( AccentedChars::Translit, $service->getAccentOperationType() );
				self::assertSame( $scraperWithActions, $factory );
			},
		];

		$scraperWithActions->generateDataIterator( $serviceMock, $actions, ignoreCache: true );
	}
}
