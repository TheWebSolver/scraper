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
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Attributes\CollectUsing;
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
		$marshaller = new MarshallTableRow( $invalidCountMsg );

		if ( $invalidCountMsg ) {
			$this->expectException( ScraperError::class );
			$this->expectExceptionMessage( sprintf( $invalidCountMsg, 4, 'name", "title", "address", "age' ) );
		}

		$tracer->addTransformer( $marshaller, Table::Row )->inferFrom( self::TABLE_INVALID_COUNT, normalize: false );

		$iterator = $tracer->getTableData()[ $tracer->getTableId( true ) ];

		$this->assertInstanceOf( Generator::class, $iterator );
		$this->assertSame( 0, $iterator->key() );
	}

	/**  @param TableTracer<string> $tracer */
	#[Test]
	#[DataProvider( 'provideTableTracerWithKeys' )]
	public function itIndexesDatasetWithProvidedKey( TableTracer $tracer ): void {
		$keyValue = [ [ 'name', 'John Doe' ], [ 'age', '22' ] ];

		foreach ( $keyValue as [$key, $value] ) {
			// Needs new $tracer() each time coz fixture resets table once $service->parse() is invoked.
			$service    = new TableScrapingService( new $tracer() );
			$marshaller = new MarshallTableRow( Indexable::INVALID_COUNT, $key );

			$service
				->withCachePath( DOMDocumentFactoryTest::RESOURCE_PATH, 'single-table.html' )
				->getTracer()
				->addTransformer( $marshaller, Table::Row );

			$iterator = $service->parse();

			$this->assertSame( $value, $iterator->key(), $tracer::class );
		}
	}

	/** @return mixed[] */
	public static function provideTableTracerWithKeys(): array {
		return [
			[ new #[CollectUsing( DevDetails::class )] class() extends StringTableTracerWithAccents {} ],
			[ new #[CollectUsing( DevDetails::class )] class() extends StringTableTracerWithAccents {}, Indexable::INVALID_COUNT ],
			[ new #[CollectUsing( DevDetails::class )] class() extends NodeTableTracerWithAccents {} ],
			[ new #[CollectUsing( DevDetails::class )] class() extends NodeTableTracerWithAccents {}, Indexable::INVALID_COUNT ],
		];
	}
}
