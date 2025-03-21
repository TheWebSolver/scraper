<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use Closure;
use BackedEnum;
use DOMElement;
use ValueError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Scraper\TableScraper;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Interfaces\Scrapable;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;
use TheWebSolver\Codegarage\Scraper\Interfaces\Collectable;

class TableScraperTest extends TestCase {
	private Scrapable $scraper; // @phpstan-ignore-line

	protected function setUp(): void {
		$this->scraper = new #[ScrapeFrom( 'Test', url: 'https://scraper.test', filename: 'table.html' )] class()
			extends TableScraper {
			public function __construct() {
				parent::__construct( collectableClass: DeveloperType::class );
			}

			protected function defaultCachePath(): string {
				return DOMDocumentFactoryTest::RESOURCE_PATH;
			}

			protected function isTargetedTable( DOMElement $node ): bool {
				return 'caption' === $node->firstChild?->nodeName;
			}

			protected function collectableEnum(): string {
				return DeveloperType::class;
			}
		};
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
			$iterator->current()
		);

		$iterator->next();

		$this->assertSame(
			array(
				'name'    => 'Lorem Ipsum',
				'title'   => 'JS Developer',
				'address' => 'Bkt',
			),
			$iterator->current()
		);

		$iterator->next();

		$this->assertFalse( $iterator->valid() );
	}

	#[Test]
	public function itThrowsExceptionWhenScrapedDataDoesNotMatchCollectionLength(): void {
		$table = '
		<table>

		  <caption></caption>
		  <!-- above caption required as first child to be targeted for this test scraper. -->

		  <tbody>
				<tr>
				  <!-- "DeveloperType::Name" -->
				  <td>John Doe</td>

				  <!-- "DeveloperType::Title" -->
				  <td>Developer</td>

				  <!-- third <td> also required for "DeveloperType::Address". Hence, exception thrown. -->
				</tr>
		  </tbody>
		</table>
		';

		$this->expectException( ScraperError::class );
		$this->expectExceptionMessage(
			sprintf( Collectable::INVALID_COUNT_MESSAGE, 3, implode( '", "', $this->scraper->getKeys() ) )
		);

		$this->scraper->parse( $table )->current();
	}

	#[Test]
	#[DataProvider( 'providesInvalidTableData' )]
	public function itThrowsExceptionWhenEachScrapedDataIsValidated( string $content, DeveloperType $type ): void {
		$table = "<table> <caption></caption> <tbody> {$content} </tbody></table>";

		$this->expectExceptionMessage( $type->errorMsg() );
		$this->scraper->parse( $table )->current();
	}

	/** @return mixed[] */
	public static function providesInvalidTableData(): array {
		return array(
			array( '<tr><td>FirstName-LastName</td><td>Title</td><td>Address</td></tr>', DeveloperType::Name ),
			array( '<tr><td>FirstName LastName</td><td>This is a very long developer title</td><td>Address</td></tr>', DeveloperType::Title ),
			array( '<tr><td>FirstName LastName</td><td>Title</td><td>Addr3ss</td></tr>', DeveloperType::Address ),
		);
	}

	#[Test]
	public function itOnlyCollectsDataWithRequestedKeys(): void {
		$this->scraper->useKeys( $requestedKeys = array( 'name', 'address' ) );

		$iterator = $this->scraper->parse( $this->scraper->fromCache() );

		$this->assertSame( 0, $iterator->key() );

		foreach ( $requestedKeys as $key ) {
			$this->assertArrayHasKey( $key, $iterator->current() ); // @phpstan-ignore-line
		}

		$this->assertArrayNotHasKey( 'title', $iterator->current() ); // @phpstan-ignore-line
	}

	#[Test]
	public function itYieldsKeyAsValueOfRequestedIndexKey(): void {
		$this->scraper->useKeys( DeveloperType::class, DeveloperType::Name );

		$iterator = $this->scraper->parse( $this->scraper->fromCache() );

		$this->assertSame( 'John Doe', $iterator->key() );
		$this->assertSame(
			array(
				'name'    => 'John Doe',
				'title'   => 'PHP Developer',
				'address' => 'Ktm',
			),
			$iterator->current()
		);
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

enum DeveloperType: string implements Collectable {
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

	public static function toArray( string|BackedEnum ...$except ): array {
		$cases = ! $except
			? self::cases()
			: array_filter( self::cases(), static fn( self $i ) => ! in_array( $i, $except, strict: true ) );

		return array_column( $cases, column_key: 'value' );
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
