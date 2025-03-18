<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use Closure;
use BackedEnum;
use DOMElement;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Depends;
use TheWebSolver\Codegarage\Scraper\TableScraper;
use TheWebSolver\Codegarage\Scraper\Interfaces\Scrapable;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;
use TheWebSolver\Codegarage\Scraper\Interfaces\Collectable;

class TableScraperTest extends TestCase {
	private Scrapable $scraper; // @phpstan-ignore-line

	protected function setUp(): void {
		$this->scraper = new #[ScrapeFrom( 'Test', url: 'https://scraper.test', filename: 'table.html' )] class()
			extends TableScraper {
			public function __construct() {
				parent::__construct( collectable: DeveloperType::class );
			}

			protected function defaultCachePath(): string {
				return DOMDocumentFactoryTest::RESOURCE_PATH;
			}

			protected function isTargetedTable( DOMElement $node ): bool {
				return 'caption' === $node->firstChild?->nodeName;
			}

			protected function getCollectableNames(): array {
				$keys = array( 'name', 'title', 'address' );

				return array_combine( $keys, $keys );
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
	public function itReturnsDefaultValuesUsingGetters(): string {
		$this->assertIsString( $table = $this->scraper->fromCache() );
		$this->assertSame( DOMDocumentFactoryTest::RESOURCE_PATH . 'table.html', $this->scraper->getCachePath() );
		$this->assertSame( 'https://scraper.test', $this->scraper->getSourceUrl() );
		$this->assertTrue( $this->scraper->hasCache() );
		$this->assertNull( $this->scraper->getDiacritic() );

		return $table;
	}

	#[Test]
	#[Depends( 'itReturnsDefaultValuesUsingGetters' )]
	public function itGeneratesTableDataOneAtATime( string $htmlTable ): void {
		$iterator = $this->scraper->parse( $htmlTable );

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
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

enum DeveloperType: string implements Collectable {
	case Name    = 'name';
	case Title   = 'title';
	case Address = 'address';

	public static function type(): string {
		return 'Developer Names';
	}

	public function length(): int {
		return 30;
	}

	public function errorMsg(): string {
		return 'This is an error msg';
	}

	public static function invalidCountMsg(): string {
		return self::type() . ' ' . self::INVALID_COUNT_MESSAGE;
	}

	public static function toArray( BackedEnum ...$filter ): array {
		$cases = ! $filter
			? self::cases()
			: array_filter( self::cases(), static fn( self $i ) => ! in_array( $i, $filter, strict: true ) );

		return array_column( $cases, column_key: 'value', index_key: 'name' );
	}

	public function isCharacterTypeAndLength( string $value ): bool {
		return ! empty( $value );
	}

	public static function walkForTypeVerification( string $data, string $key, Closure $handler ): bool {
		return true;
	}
}
