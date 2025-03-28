<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use Closure;
use Iterator;
use BackedEnum;
use DOMElement;
use ValueError;
use ArrayObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Scraper\Data\CollectionSet;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\SingleTableScraper;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;
use TheWebSolver\Codegarage\Scraper\Attributes\CollectFrom;
use TheWebSolver\Codegarage\Scraper\Interfaces\Collectable;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Marshaller\TableRowMarshaller;

class TableScraperTest extends TestCase {
	private HtmlTableScraper $scraper;

	/**
	 * @param Closure(string|DOMElement, int, TableTracer<mixed,string>): string $marshaller
	 * @return Transformer<string>
	 */
	private function withTransformedTDUsing( Closure $marshaller ): Transformer {
		return new class( $marshaller ) implements Transformer {
			/** @param Closure(string|DOMElement, int, TableTracer<mixed,string>): string $marshaller */
			public function __construct( private Closure $marshaller ) {}

			/** @param TableTracer<mixed,string> $tracer */
			public function transform( string|DOMElement $element, int $position, TableTracer $tracer ): string {
				return ( $this->marshaller )( $element, $position, $tracer );
			}
		};
	}

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
		$this->assertNull( $this->scraper->getDiacritic() );
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
		$tr = new /**@template-extends TableRowMarshaller<string> */ class( DeveloperDetails::class )
		extends TableRowMarshaller{};

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
			sprintf( Collectable::INVALID_COUNT_MESSAGE, 3, 'name", "title", "address' )
		);

		$this->scraper->withTransformers( compact( 'tr' ) )->parse( $table )->current();
	}

	/** @param ?array{0:array-key,1:string[]} $expectedValue */
	#[Test]
	#[DataProvider( 'providesValidAndInvalidTableData' )]
	public function itThrowsExceptionWhenEachScrapedDataIsValidated(
		string $content,
		DeveloperDetails $type,
		?array $expectedValue = null
	): void {
		$tr = new /**@template-extends TableRowMarshaller<string> */ class( DeveloperDetails::class, 'address' )
		extends TableRowMarshaller{};

		$table = "<table> <caption></caption> <tbody> {$content} </tbody></table>";
		$td    = $this->withTransformedTDUsing( $this->scraper->validateTableData( ... ) );

		if ( null === $expectedValue ) {
			$this->expectExceptionMessage( $type->errorMsg() );
		}

		$iterator      = $this->scraper->withTransformers( compact( 'tr', 'td' ) )->parse( $table );
		[$key, $value] = $expectedValue ?? array( null, null );

		$this->assertSame( $key, $iterator->key() );
		$this->assertSame( $value, $iterator->current()->getArrayCopy() );
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
		$td = $this->withTransformedTDUsing( $this->scraper->validateTableData( ... ) );

		$this->scraper->withTransformers( compact( 'td' ) )->useKeys( $requestedKeys = array( 'name', 'address' ) );

		$iterator = $this->scraper->parse( $this->scraper->fromCache() );
		$current  = $iterator->current();

		$this->assertSame( 0, $iterator->key() );

		foreach ( $requestedKeys as $key ) {
			$this->assertArrayHasKey( $key, $current->getArrayCopy() );
		}

		$this->assertArrayNotHasKey( 'title', $current->getArrayCopy() );
	}

	#[Test]
	public function itYieldsKeyAsValueThatOffsetsDeveloperDetailsEnumCaseValue(): void {
		$td = $this->withTransformedTDUsing( $this->scraper->validateTableData( ... ) );
		$tr = new /** @template-implements Transformer<CollectionSet<string>> */ class()
		implements Transformer{
			/** @param TableTracer<string,string> $tracer */
			public function transform( string|DOMElement $element, int $position, TableTracer $tracer ): mixed {
				assert( $element instanceof DOMElement );

				$data = $tracer->inferTableDataFrom( $element->childNodes );

				return new CollectionSet( $data[ DeveloperDetails::Name->value ], new ArrayObject( $data ) );
			}
		};

		$this->scraper->withTransformers( compact( 'td', 'tr' ) );

		$iterator = $this->scraper->parse( $this->scraper->fromCache() );

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
		$scraper  = new class( $iterator ) extends SingleTableScraper {
			public function __construct( private readonly Iterator $iterator ) {
				$this->useKeys( $this->collectableFromConcrete( DeveloperDetails::class )->items );
			}

			public function parse( string $content ): Iterator {
				return $this->iterator;
			}

			protected function defaultCachePath(): string {
				return '';
			}
		};

		$this->assertCount( 3, $scraper->getKeys() );

		$scraper = new class( $iterator ) extends SingleTableScraper {
			public function __construct( private readonly Iterator $iterator ) {
				$this->useKeys(
					$this->collectableFromConcrete( DeveloperDetails::class, DeveloperDetails::Name, 'address' )->items
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

/**
 * @template-extends SingleTableScraper<string>
 */
#[ScrapeFrom( 'Test', url: 'https://scraper.test', filename: 'table.html' )]
#[CollectFrom( DeveloperDetails::class )]
class HtmlTableScraper extends SingleTableScraper {
	public function parse( string $content ): Iterator {
		yield from $this->validateCurrentTableParsedData( $content );
	}

	public function validateTableData( string|DOMElement $element ): string {
		$content    = trim( is_string( $element ) ? $element : $element->textContent );
		$columnName = $this->getCurrentColumnName() ?? '';
		$value      = $this->isRequestedKey( $columnName ) ? $content : '';

		$value && ( $source = $this->getCollectionSource() ) &&
			$source->concrete::validate( $value, $columnName, ScraperError::withSourceMsg( ... ) );

		return $value;
	}

	protected function defaultCachePath(): string {
		return DOMDocumentFactoryTest::RESOURCE_PATH;
	}

	protected function isTargetedTable( DOMElement $node ): bool {
		return 'caption' === $node->firstChild?->nodeName;
	}
}

enum DeveloperDetails: string implements Collectable {
	case Name    = 'name';
	case Title   = 'title';
	case Address = 'address';

	public function errorMsg(): string {
		return match ( $this ) {
			self::Name    => 'First & Last name must be separated by space.',
			self::Title   => 'Title must be less than or equal to 15 characters',
			self::Address => 'Address can only be alpha characters.',
		};
	}

	public static function label(): string {
		return 'Developer Names';
	}

	public static function invalidCountMsg(): string {
		return self::label() . ' ' . self::INVALID_COUNT_MESSAGE;
	}

	public static function toArray(): array {
		return array_column( self::cases(), column_key: 'value' );
	}

	public static function validate( mixed $data, string $item, ?Closure $handler = null ): bool {
		is_string( $data ) || throw new ValueError( 'Data must be string.' );

		return match ( self::from( $item ) ) {
			self::Name    => str_contains( $data, ' ' ) || ( $handler && $handler( self::Name->errorMsg() ) ),
			self::Title   => strlen( $data ) <= 15 || ( $handler && $handler( self::Title->errorMsg() ) ),
			self::Address => ctype_alpha( $data ) || ( $handler && $handler( self::Address->errorMsg() ) ),
		};
	}
}
