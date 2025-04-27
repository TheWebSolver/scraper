<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Table;

use Generator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Test\Fixture\DevDetails;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Test\DOMDocumentFactoryTest;
use TheWebSolver\Codegarage\Scraper\Interfaces\Indexable;
use TheWebSolver\Codegarage\Scraper\Attributes\CollectFrom;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Marshaller\MarshallTableRow;
use TheWebSolver\Codegarage\Test\Fixture\Table\TableScrapingService;
use TheWebSolver\Codegarage\Test\Fixture\Table\NodeTableTracerWithAccents;
use TheWebSolver\Codegarage\Test\Fixture\Table\StringTableTracerWithAccents;

class TraceTableWithRowMarshallerTest extends TestCase {
	private const TABLE_INVALID_COUNT = '<table><tbody><tr><td>John Doe</td><td>22</td></tr></tbody></table>';

	/** @param TableTracer<string> $tracer */
	#[Test]
	#[DataProvider( 'provideTableTracerWithKeys' )]
	public function itThrowsExceptionWhenScrapedDataDoesNotMatchCollectionLength(
		TableTracer $tracer,
		string $invalidCountMsg = ''
	): void {
		$service = new TableScrapingService( $tracer );

		/** @var MarshallTableRow<string> */
		$marshaller = new MarshallTableRow( $invalidCountMsg );

		$service->getTableTracer()->addTransformer( Table::Row, $marshaller );

		$iterator = $service->parse( self::TABLE_INVALID_COUNT );

		if ( $invalidCountMsg ) {
			$this->expectException( ScraperError::class );
			$this->expectExceptionMessage( sprintf( $invalidCountMsg, 4, 'name", "title", "address", "age' ) );
		} else {
			$this->assertInstanceOf( Generator::class, $iterator );
			$this->assertSame( 0, $iterator->key() );
		}

		$iterator->current();
	}

	/**  @param TableTracer<string> $tracer */
	#[Test]
	#[DataProvider( 'provideTableTracerWithKeys' )]
	public function itIndexesDatasetWithProvidedKey( TableTracer $tracer ): void {
		$content  = file_get_contents( DOMDocumentFactoryTest::RESOURCE_PATH . DIRECTORY_SEPARATOR . 'single-table.html' ) ?: '';
		$keyValue = [ [ 'name', 'John Doe' ], [ 'age', '22' ] ];

		foreach ( $keyValue as [$key, $value] ) {
			// Needs new $tracer() each time coz fixture resets table once $service->parse() is invoked.
			$service = new TableScrapingService( new $tracer() );

			/** @var MarshallTableRow<string> */
			$marshaller = new MarshallTableRow( Indexable::INVALID_COUNT, $key );

			$service->getTableTracer()->addTransformer( Table::Row, $marshaller );

			$iterator = $service->parse( $content );

			$this->assertSame( $value, $iterator->key(), $tracer::class );
		}
	}

	/** @return mixed[] */
	public static function provideTableTracerWithKeys(): array {
		return [
			[ new #[CollectFrom( DevDetails::class )] class() extends StringTableTracerWithAccents {} ],
			[ new #[CollectFrom( DevDetails::class )] class() extends StringTableTracerWithAccents {}, Indexable::INVALID_COUNT ],
			[ new #[CollectFrom( DevDetails::class )] class() extends NodeTableTracerWithAccents {} ],
			[ new #[CollectFrom( DevDetails::class )] class() extends NodeTableTracerWithAccents {}, Indexable::INVALID_COUNT ],
		];
	}
}
