<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Table;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Test\Fixture\StripTags;
use TheWebSolver\Codegarage\Test\Fixture\DevDetails;
use TheWebSolver\Codegarage\Scraper\Event\TableTraced;
use TheWebSolver\Codegarage\Scraper\Enums\AccentedChars;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Attributes\CollectUsing;
use TheWebSolver\Codegarage\Test\Fixture\Table\NodeTableTracer;
use TheWebSolver\Codegarage\Test\Fixture\Table\StringTableTracer;
use TheWebSolver\Codegarage\Test\Fixture\Table\TableScrapingService;
use TheWebSolver\Codegarage\Test\Fixture\Table\NodeTableTracerWithAccents;
use TheWebSolver\Codegarage\Scraper\Decorator\TranslitAccentedIndexableItem;
use TheWebSolver\Codegarage\Test\Fixture\Table\StringTableTracerWithAccents;

class TableScrapingServiceTest extends TestCase {
	#[Test]
	public function itScrapesTableUsingTableTracerAndYieldsDataset(): void {
		foreach ( [ StringTableTracer::class, NodeTableTracer::class ] as $tracer ) {
			$service     = new TableScrapingService( new $tracer() );
			$iterator    = $service->parse();
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
	public function itScrapesAndUsesTracedHeaderAsDatasetKeys(): void {
		foreach ( [ StringTableTracer::class, NodeTableTracer::class ] as $tracer ) {
			$service = new TableScrapingService( new $tracer() );

			$service->getTracer()->addEventListener(
				static function ( TableTraced $event ) {
					$event->tracer->setIndicesSource(
						CollectUsing::listOf(
							// @phpstan-ignore-next-line
							$event->tracer->getTableHead()[ $event->tracer->getTableId( current: true ) ]->toArray()
						)
					);
				},
				structure: Table::Row
			);

			$iterator = $service->parse();
			$johnDoe  = $iterator->current()->getArrayCopy();

			if ( NodeTableTracer::class === $tracer ) {
				$this->assertSame(
					[
						'Name'       => 'John Doe',
						'Title'      => 'PHP Develôper',
						'Address[b]' => 'Ktm',
						'Dev Age'    => '22',
					],
					$johnDoe,
					$tracer
				);
			} else {
				$this->assertSame(
					[
						'Name'    => 'John Doe',
						'<span class="nowrap">Title</span>' => 'PHP Devel&ocirc;per',
						'<span>Address<a href="#location-anchor">&#91;b&#93;</a></span>' => '<a href="/location" title="Developer location">Ktm</a>',
						'Dev Age' => '22',
					],
					$johnDoe,
					$tracer
				);
			}//end if
		}//end foreach
	}

	#[Test]
	public function itCleansTracedDataUsingTransformerForBothDatasetKeysAndValues(): void {
		$stripTags = new StripTags();

		foreach ( [ StringTableTracer::class, NodeTableTracer::class ] as $tracer ) {
			$service = new TableScrapingService( new $tracer() );

			$service->getTracer()->addEventListener(
				static function ( TableTraced $event ) {
					$event->tracer->setIndicesSource(
						// @phpstan-ignore-next-line
						CollectUsing::listOf( $event->tracer->getTableHead()[ $event->tracer->getTableId( true ) ]->toArray() )
					);
				},
				structure: Table::Row
			)->addTransformer( $stripTags, Table::Head )->addTransformer( $stripTags, Table::Column );

			$iterator = $service->parse();

			$this->assertSame(
				[
					'Name'    => 'John Doe',
					'Title'   => 'PHP Develôper',
					'Address' => 'Ktm',
					'Dev Age' => '22',
				],
				$iterator->current()->getArrayCopy(),
				$tracer
			);
		}//end foreach
	}

	/** @param list<string> $keys */
	#[Test]
	#[DataProvider( 'provideTranslitArgsToOperateOnAccentedCharacters' )]
	public function itScrapesAndTranslitAccentedCharacters( AccentedChars $action, string $expectedTitle, array $keys ): void {
		$tracerWithAccents = [
			new #[CollectUsing( DevDetails::class )] class( $keys ) extends StringTableTracerWithAccents {
				/** @param list<string> $accentedItemIndices */
				public function __construct( protected array $accentedItemIndices ) {}
			},
			new #[CollectUsing( DevDetails::class )] class( $keys ) extends NodeTableTracerWithAccents {
				/** @param list<string> $accentedItemIndices */
				public function __construct( protected array $accentedItemIndices ) {}
			},
		];

		foreach ( $tracerWithAccents as $tracer ) {
			$accentedTracer = new $tracer( $keys );
			$service        = new TableScrapingService( $accentedTracer );
			$transformer    = new TranslitAccentedIndexableItem( new StripTags() );

			$accentedTracer->addTransformer( $transformer, Table::Column )->setAccentOperationType( $action );

			$iterator = $service->parse();

			$this->assertSame( $expectedTitle, $iterator->current()->getArrayCopy()['title'] );
		}
	}

	/** @return mixed[] */
	public static function provideTranslitArgsToOperateOnAccentedCharacters(): array {
		return [
			[ AccentedChars::Escape, 'PHP Develôper', [] ],
			[ AccentedChars::Translit, 'PHP Develôper', [ 'name', 'age' ] ],
			[ AccentedChars::Escape, 'PHP Develôper', [ 'title' ] ],
			[ AccentedChars::Translit, 'PHP Developer', [] ],
			[ AccentedChars::Translit, 'PHP Developer', [ 'title', 'address' ] ],
		];
	}

	/**
	 * @param TableTracer<string> $tracer
	 * @param mixed[]             $expected
	*/
	#[Test]
	#[DataProvider( 'provideDatasetKeysWithAllEnumCases' )]
	public function itScrapesAndUsesAllEnumCasesAsDatasetKeys( TableTracer $tracer, array $expected ): void {
		$service  = new TableScrapingService( $tracer );
		$iterator = $service->parse();

		$this->assertSame( $expected, $iterator->current()->getArrayCopy(), $tracer::class );
	}

	/** @return mixed[] */
	public static function provideDatasetKeysWithAllEnumCases(): array {
		return [
			[
				new #[CollectUsing( DevDetails::class )] class() extends StringTableTracerWithAccents {},
				[
					'name'    => 'John Doe',
					'title'   => 'PHP Devel&ocirc;per',
					'address' => '<a href="/location" title="Developer location">Ktm</a>',
					'age'     => '22',
				],
			],
			[
				new #[CollectUsing( DevDetails::class )] class() extends NodeTableTracerWithAccents {},
				[
					'name'    => 'John Doe',
					'title'   => 'PHP Develôper',
					'address' => 'Ktm',
					'age'     => '22',
				],
			],
		];
	}

	/**
	 * @param TableTracer<string> $tracer
	 * @param mixed[]             $expected
	*/
	#[Test]
	#[DataProvider( 'providePartialDatasetKeys' )]
	public function itScrapesAndUsesPartialEnumCasesAsDatasetKeys( TableTracer $tracer, array $expected ): void {
		$service  = new TableScrapingService( $tracer );
		$iterator = $service->parse();

		$this->assertSame( $expected, $iterator->current()->getArrayCopy(), $tracer::class );
	}

	/** @return mixed[] */
	public static function providePartialDatasetKeys(): array {
		return [
			'String: Using PHP Attribute' => [
				new #[CollectUsing( DevDetails::class, null, DevDetails::Name, null, DevDetails::Address )] class()
				extends StringTableTracerWithAccents {},
				[
					'name'    => 'John Doe',
					'address' => '<a href="/location" title="Developer location">Ktm</a>',
				],
			],
			'Node: Using PHP Attribute' => [
				new #[CollectUsing( DevDetails::class, null, DevDetails::Name, null, DevDetails::Address )] class()
				extends NodeTableTracerWithAccents {},
				[
					'name'    => 'John Doe',
					'address' => 'Ktm',
				],
			],
			'String: Using method call' => [
				new class() extends StringTableTracerWithAccents {
					public function __construct() {
						$this->addEventListener(
							static fn( TableTraced $e ) => $e->tracer->setIndicesSource(
								new CollectUsing( DevDetails::class, null, DevDetails::Name, null, DevDetails::Address )
							),
							structure: Table::Row
						);
					}
				},
				[
					'name'    => 'John Doe',
					'address' => '<a href="/location" title="Developer location">Ktm</a>',
				],
			],
			'Node: Using method call' => [
				new class() extends NodeTableTracerWithAccents {
					public function __construct() {
						$this->addEventListener(
							static fn( TableTraced $e ) => $e->tracer->setIndicesSource(
								new CollectUsing( DevDetails::class, null, DevDetails::Name, null, DevDetails::Address )
							),
							structure: Table::Row
						);
					}
				},
				[
					'name'    => 'John Doe',
					'address' => 'Ktm',
				],
			],
		];
	}
}
