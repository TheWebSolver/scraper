<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use Closure;
use BackedEnum;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Scraper\Interfaces\Collectable;
use TheWebSolver\Codegarage\Scraper\Traits\CollectionAware;
use TheWebSolver\Codegarage\Scraper\Interfaces\CollectionSet;

class CollectionSetTest extends TestCase {
	private CollectionSet $collector;

	protected function setUp(): void {
		$this->collector = new class() implements CollectionSet {
			use CollectionAware {
				CollectionAware::getKeysFromCollectableClass as public;
			}

			/** @return class-string<Collectable> */
			protected function collectableClass(): string {
				return SetEnumStub::class;
			}
		};
	}

	protected function tearDown(): void {
		unset( $this->collector );
	}

	#[Test]
	public function itReturnsDefaultValueFromGetters(): void {
		$this->assertSame( array(), $this->collector->getKeys() );
		$this->assertNull( $this->collector->getIndexKey() );
	}

	/**
	 * @param array{0:list<string>,1:?string}       $expected
	 * @param class-string<BackedEnum>|list<string> $keys
	 */
	#[Test]
	#[DataProvider( 'provideCollectionKeys' )]
	public function itSetsScrapedDataCollectSetKeysAndIndexKeyBasedOnArgPassed(
		array $expected,
		string|array $keys,
		string|BackedEnum|null $key = null,
	): void {
		[$expectedKeys, $expectedKey] = $expected;

		$this->collector->useKeys( $keys, $key );

		$this->assertSame( $expectedKeys, $this->collector->getKeys() );
		$this->assertSame( $expectedKey, $this->collector->getIndexKey() );
	}

	/** @return mixed[] */
	public static function provideCollectionKeys(): array {
		return array(
			array( array( array(), null ), array() ),
			array( array( array( 'name', 'title', 'address' ), null ), array( 'name', 'title', 'address' ) ),
			array( array( array( 'name', 'title', 'address' ), 'title' ), array( 'name', 'title', 'address' ), 'title' ),
			array( array( array( 'test' ), 'test' ), SetEnumStub::class, SetEnumStub::Test ),
		);
	}

	#[Test]
	public function itExtractsCollectionSetNamesFromEnumCases(): void {
		$this->collector->useKeys( array(), SetEnumStub::Test );

		// @phpstan-ignore-next-line
		/** @disregard P1013 Undefined method */ $this->collector->useKeys( $this->collector->getKeysFromCollectableClass() );

		$this->assertSame( array( 'test' ), $this->collector->getKeys() );
		$this->assertSame( 'test', $this->collector->getIndexKey() );
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

enum SetEnumStub: string implements Collectable {
	case Test = 'test';

	public static function label(): string {
		return '';
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	public static function toArray( string|BackedEnum ...$except ): array {
		return array_column( self::cases(), column_key: 'value' );
	}

	public static function invalidCountMsg(): string {
		return '';
	}

	public static function validate( mixed $data, string $item, Closure $handler = null ): bool {
		return true;
	}
}
