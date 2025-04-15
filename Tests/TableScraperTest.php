<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use Iterator;
use BackedEnum;
use DOMElement;
use ArrayObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Data\CollectionSet;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\SingleTableScraper;
use TheWebSolver\Codegarage\Scraper\Interfaces\KeyMapper;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;
use TheWebSolver\Codegarage\Scraper\Attributes\CollectFrom;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\AccentedSingleTableScraper;
use TheWebSolver\Codegarage\Scraper\Marshaller\TableRowMarshaller;
use TheWebSolver\Codegarage\Scraper\Traits\Table\HtmlTableFromNode;
use TheWebSolver\Codegarage\Scraper\Traits\Table\HtmlTableFromString;

class TableScraperTest extends TestCase {
	private HtmlTableScraper $scraper;

	protected function setUp(): void {
		$this->scraper = new HtmlTableScraper();
	}

	protected function tearDown(): void {
		unset( $this->scraper );
	}

	#[Test]
	public function itReturnsDefaultValuesUsingGetters(): void {
		$this->assertNotEmpty( $this->scraper->fromCache() );
		$this->assertSame( DOMDocumentFactoryTest::RESOURCE_PATH . 'table.html', $this->scraper->getCachePath() );
		$this->assertSame( 'https://scraper.test', $this->scraper->getSourceUrl() );
		$this->assertTrue( $this->scraper->hasCache() );
	}

	#[Test]
	public function itGeneratesTableDataOneAtATime(): void {
		$iterator = $this->scraper->parse( $this->scraper->fromCache() );

		$this->assertSame(
			array(
				'name'    => 'John Doe',
				'title'   => 'PHP Developer',
				'address' => 'Ktm',
			),
			$iterator->current()->getArrayCopy()
		);

		$iterator->next();

		$this->assertSame(
			array(
				'name'    => 'Lorem Ipsum',
				'title'   => 'JS Developer',
				'address' => 'Bkt',
			),
			$iterator->current()->getArrayCopy()
		);

		$iterator->next();

		$this->assertFalse( $iterator->valid() );
	}

	#[Test]
	public function itThrowsExceptionWhenScrapedDataDoesNotMatchCollectionLength(): void {
		$tr = new TableRowMarshaller( KeyMapper::INVALID_COUNT );

		$table = '
		<table>

		  <caption></caption>
		  <!-- above caption required as first child to be targeted for this test scraper. -->

		  <tbody>
				<tr>
				  <!-- "DeveloperDetails::Name" -->
				  <td>John Doe</td>

				  <!-- "DeveloperDetails::Title" -->
				  <td>Developer</td>

				  <!-- third <td> also required for "DeveloperDetails::Address". Hence, exception thrown. -->
				</tr>
		  </tbody>
		</table>
		';

		$this->expectException( ScraperError::class );
		$this->expectExceptionMessage(
			sprintf( KeyMapper::INVALID_COUNT, 3, 'name", "title", "address' )
		);

		// @phpstan-ignore-next-line -- Ignore $tr generic type.
		$this->scraper->addTransformer( Table::Row, $tr )->parse( $table )->current();
	}

	#[Test]
	public function itTranslitAndCollectOnlySubsetOfColumnProvided(): void {
		$scraper = new AccentedCharScraper();
		$table   = '
			<table>
				<caption></caption>
				<tbody>
					<tr><td>Valid Name</td><td>Develôper</td><td>Curaçao</td></tr>
				</tbody>
			</table>
		';

		$this->assertSame(
			array(
				'title'   => 'Develôper',
				'address' => 'Curaçao',
			),
			$scraper->parse( $table )->current()->getArrayCopy()
		);

		$scraper = new AccentedCharScraper( DeveloperDetails::Title->value );

		$scraper->setAccentOperationType( AccentedCharScraper::ACTION_TRANSLIT );

		$this->assertSame(
			array(
				'title'   => 'Developer',
				'address' => 'Curaçao',
			),
			$scraper->parse( $table )->current()->getArrayCopy()
		);
	}

	/** @return mixed[] */
	public static function providesValidAndInvalidTableData(): array {
		return array(
			array(
				'<tr><td>FirstName-LastName</td><td>Title</td><td>Address</td></tr>',
				DeveloperDetails::Name,
			),
			array(
				'<tr><td>FirstName LastName</td><td>This is a very long developer title</td><td>Address</td></tr>',
				DeveloperDetails::Title,
			),
			array(
				'<tr><td>FirstName LastName</td><td>Title</td><td>Addr3ss</td></tr>',
				DeveloperDetails::Address,
			),
			array(
				'<tr><td>Valid Name</td><td>Valid Title</td><td>Located</td></tr>',
				DeveloperDetails::Address,
				array(
					'Located',
					array(
						'name'    => 'Valid Name',
						'title'   => 'Valid Title',
						'address' => 'Located',
					),
				),
			),
		);
	}

	#[Test]
	public function itOnlyCollectsDataWithRequestedKeys(): void {
		$requestedKeys = array( 'name', 'address' );
		$validateKeys  = new /** @template-implements Transformer<HtmlTableScraper,string> */ class( $requestedKeys )
		implements Transformer {
			/** @param string[] $requestedKeys */
			public function __construct( private array $requestedKeys ) {}

			public function transform( mixed $element, object $tracer ): mixed {
				// @phpstan-ignore-next-line -- $textContent is not null.
				$content    = trim( is_string( $element ) ? $element : $element->textContent );
				$columnName = $tracer->getCurrentTracedItemIndex() ?? '';
				$value      = in_array( $columnName, $this->requestedKeys, true ) ? $content : '';

				return $value;
			}
		};

		$this->scraper->addTransformer( Table::Column, $validateKeys );

		$iterator = $this->scraper->parse( $this->scraper->fromCache() );
		$dataset  = $iterator->current()->getArrayCopy();

		$this->assertSame( 0, $iterator->key() );
		$this->assertArrayNotHasKey( 'title', $dataset );

		foreach ( $requestedKeys as $key ) {
			$this->assertArrayHasKey( $key, $dataset );
		}
	}

	#[Test]
	public function itYieldsKeyAsValueThatOffsetsDeveloperDetailsEnumCaseValue(): void {
		$collectDataset = new /** @template-implements Transformer<HtmlTableScraper,CollectionSet<string>> */ class()
		implements Transformer{
			/** @param TableTracer<string> $tracer */
			public function transform( mixed $element, object $tracer ): mixed {
				assert( $element instanceof DOMElement );

				$data = $tracer->inferTableDataFrom( $element->childNodes );

				return new CollectionSet( $data[ DeveloperDetails::Name->value ], new ArrayObject( $data ) );
			}
		};

		$iterator = $this->scraper
			->addTransformer( Table::Row, $collectDataset )
			->parse( $this->scraper->fromCache() );

		$this->assertSame( 'John Doe', $iterator->key() );
		$this->assertSame(
			array(
				'name'    => 'John Doe',
				'title'   => 'PHP Developer',
				'address' => 'Ktm',
			),
			$iterator->current()->getArrayCopy()
		);
	}

	#[Test]
	public function itRegistersCollectableSourceUsingEnumName(): void {
		$iterator = $this->createStub( Iterator::class );
		$scraper  = new /** @template-extends SingleTableScraper<string> */ class( $iterator ) extends SingleTableScraper {
			/** @use HTMLTableFromString<string> */
			use HtmlTableFromString;

			public function __construct( private readonly Iterator $iterator ) {
				$this->useKeys( $this->collectFromMappable( DeveloperDetails::class )->items );
			}

			public function parse( string $content ): Iterator {
				return $this->iterator;
			}

			protected function defaultCachePath(): string {
				return '';
			}
		};

		$this->assertCount( 3, $scraper->getKeys() );

		$scraper = new /** @template-extends SingleTableScraper<string> */ class( $iterator ) extends SingleTableScraper {
			/** @use HTMLTableFromString<string> */
			use HtmlTableFromString;

			public function __construct( private readonly Iterator $iterator ) {
				$this->useKeys(
					$this->collectFromMappable( DeveloperDetails::class, DeveloperDetails::Name, 'address' )->items
				);
			}

			public function parse( string $content ): Iterator {
				return $this->iterator;
			}

			protected function defaultCachePath(): string {
				return '';
			}
		};

		$this->assertSame( array( 'name', 'address' ), $scraper->getKeys() );
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

/** @template-extends SingleTableScraper<string> */
#[ScrapeFrom( 'Test', url: 'https://scraper.test', filename: 'table.html' )]
#[CollectFrom( DeveloperDetails::class )]
class HtmlTableScraper extends SingleTableScraper {
	/** @use HtmlTableFromNode<string> */
	use HtmlTableFromNode;

	public function parse( string $content ): Iterator {
		yield from $this->currentTableIterator( $content );
	}

	protected function defaultCachePath(): string {
		return DOMDocumentFactoryTest::RESOURCE_PATH;
	}

	protected function isTargetedTable( string|DOMElement $node ): bool {
		return $node instanceof DOMElement && 'caption' === $node->firstChild?->nodeName;
	}
}

/** @template-implements BackedEnum<string> */
enum DeveloperDetails: string {
	case Name    = 'name';
	case Title   = 'title';
	case Address = 'address';
}

/** @template-extends AccentedSingleTableScraper<string> */
#[ScrapeFrom( 'Translit Test', url: 'https://accentedCharacters.test', filename: '' )]
#[CollectFrom( DeveloperDetails::class, DeveloperDetails::Title, DeveloperDetails::Address )]
class AccentedCharScraper extends AccentedSingleTableScraper {
	/** @use HtmlTableFromString<string> */
	use HtmlTableFromString;

	public function __construct( string ...$translitNames ) {
		parent::__construct( null, null, ...$translitNames );

		$setColumnNamesWithoutName = static fn( self $s )
			=> $s->setTracedItemsIndices( $s->getKeys(), $s->getTableId( true ), /* offset: DeveloperDetails::Name */ 0 );

		$this->addEventListener( Table::TBody, $setColumnNamesWithoutName );
	}

	public function indicesWithAccentedCharacters(): array {
		return $this->transliterationColumnNames ?? array();
	}

	public function getDiacriticsList(): array {
			return array(
				'ä' => 'ae',
				'ç' => 'c',
				'ė' => 'e',
				'ô' => 'o',
			);
	}

	protected function defaultCachePath(): string {
		return DOMDocumentFactoryTest::RESOURCE_PATH;
	}
}
