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
				CollectionAware::useKeysFromCollectableEnum as public;
			}

			/** @return class-string<Collectable> */
			protected function collectableEnum(): string {
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
	 * @param array{0:string[],1:?string}        $expected
	 * @param class-string<Collectable>|string[] $keys
	 */
	#[Test]
	#[DataProvider( 'provideCollectionKeys' )]
	public function itSetsScrapedDataCollectSetKeysAndIndexKeyBasedOnArgPassed(
		array $expected,
		string|array $keys,
		string|Collectable|null $key = null,
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
		/** @disregard P1013 Undefined method */ $this->collector->useKeysFromCollectableEnum();

		$this->assertSame( array( 'test' ), $this->collector->getKeys() );
		$this->assertSame( 'test', $this->collector->getIndexKey() );
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

enum SetEnumStub: string implements Collectable {
	case Test = 'test';

	public function length(): int {
		return match ( $this ) {
			self::Test => 4,
		};
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
	public static function toArray( BackedEnum ...$filter ): array {
		return array_column( self::cases(), column_key: 'value', index_key: 'name' );
	}

	public static function invalidCountMsg(): string {
		return '';
	}

	public static function walkForTypeVerification( string $data, string $key, Closure $handler ): bool {
		return true;
	}
}
