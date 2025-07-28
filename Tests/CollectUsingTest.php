<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use BackedEnum;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Scraper\Attributes\CollectUsing;

#[CoversClass( CollectUsing::class )]
class CollectUsingTest extends TestCase {
	#[Test]
	public function itConvertsEnumCasesToString(): void {
		$collection = new CollectUsing( Collectable::class, Collectable::One );

		$this->assertSame( [ '0', '1', '2', '3', '4' ], $collection->items );
		$this->assertSame( '1', $collection->indexKey );
		$this->assertSame( [], $collection->offsets );

		$this->expectExceptionMessage( 'with invalid enum classname "' . self::class . '"' );

		new CollectUsing( self::class ); // @phpstan-ignore-line
	}

	/**
	 * @param list<null|string|BackedEnum<string>> $constructorSubsets
	 * @param list<string>                         $expectedItems
	 * @param list<int>                            $expectedOffsets
	 */
	#[Test]
	#[DataProvider( 'provideCollectableItems' )]
	public function itOffsetsInBetweenItems(
		array $constructorSubsets,
		array $expectedItems,
		array $expectedOffsets = []
	): void {
		$collection = new CollectUsing( Collectable::class, null, ...$constructorSubsets );

		$this->assertSame( $expectedItems, $collection->items );
		$this->assertSame( $expectedOffsets, $collection->offsets );
	}

	/** @return mixed[] */
	public static function provideCollectableItems(): array {
		return [
			[ [], [ '0', '1', '2', '3', '4' ] ],
			[
				[ Collectable::Zero, null, Collectable::Two ],
				[
					0 => '0',
					2 => '2',
				],
				[ 1 ],
			],
			[
				[ Collectable::Zero, null, Collectable::Two, null, null ],
				[
					0 => '0',
					2 => '2',
				],
				[ 1 ],
			],
			[
				[ null, Collectable::One, Collectable::Two, null, Collectable::Four ],
				[
					1 => '1',
					2 => '2',
					4 => '4',
				],
				[ 0, 3 ],
			],
			[
				[ Collectable::Four, null, null, Collectable::Zero, Collectable::Two ],
				[
					0 => '4',
					3 => '0',
					4 => '2',
				],
				[ 1, 2 ],
			],
			[ [ Collectable::Zero, Collectable::One, Collectable::Two ], [ '0', '1', '2' ], [] ],
		];
	}

	/**
	 * @param list<string|BackedEnum<string>>      $recomputeSubsets
	 * @param list<string>                         $expectedItems
	 * @param list<int>                            $expectedOffsets
	 * @param list<string|BackedEnum<string>|null> $constructorSubsets
	 * @covers ::with
	 * @covers ::recomputeFor
	 */
	#[Test]
	#[DataProvider( 'provideRecomputeData' )]
	public function itRecomputesOffsetsInBetweenItems(
		array $recomputeSubsets,
		array $expectedItems,
		array $expectedOffsets = [],
		array $constructorSubsets = []
	): void {
		$collection    = new CollectUsing( Collectable::class, null, ...$constructorSubsets );
		$newCollection = $collection->subsetOf( ...$recomputeSubsets );

		$this->assertSame( $expectedItems, $newCollection->items );
		$this->assertSame( $expectedOffsets, $newCollection->offsets );

		if ( [] === $recomputeSubsets ) {
			$this->assertSame( $collection, $newCollection );
		}
	}

	/** @return mixed[] */
	public static function provideRecomputeData(): array {
		return [
			[ [ '3', Collectable::Zero ], [ 3 => '3' ], [ 0, 1, 2 ], [ null, '1', null, '3' ] ],
			[ [ '2', '4', '1' ], [ 1 => '1' ], [ 0 ], [ null, '1', null, '3' ] ],
			[
				[ '3', '0', '1' ],
				[
					1 => '1',
					3 => '3',
				],
				[ 0, 2 ],
				[ null, '1', null, '3' ],
			],
			[
				[ '1', '4', '3' ],
				[
					1 => '3',
					3 => '1',
				],
				[ 0, 2 ],
				[ null, '3', null, '1' ],
			],
			[
				[ '3', Collectable::Zero ],
				[
					0 => '0',
					3 => '3',
				],
				[ 1, 2 ],
			],
			[
				[ '1', Collectable::Zero, '3' ],
				[
					0 => '0',
					1 => '1',
					3 => '3',
				],
				[ 2 ],
			],
			[
				[ '3', Collectable::Zero, Collectable::Four ],
				[
					0 => '0',
					3 => '3',
					4 => '4',
				],
				[ 1, 2 ],
			],
			[ [], [ '0', '1', '2', '3', '4' ] ],
		];
	}

	/**
	 * @param class-string<BackedEnum<string>>     $enumClass
	 * @param list<string|BackedEnum<string>|null> $cases
	*/
	#[Test]
	#[DataProvider( 'provideCasesThatThrowsExceptionForCompute' )]
	public function itThrowsExceptionWhenStringCannotResolveEnumCaseWhenInvalidSubsetProvided(
		string $enumClass,
		string $reason,
		array $cases = []
	): void {
		$this->expectExceptionMessage( $reason );

		new CollectUsing( $enumClass, null, ...$cases );
	}

	/** @return mixed[] */
	public static function provideCasesThatThrowsExceptionForCompute(): array {
		return [
			[
				Collectable::class,
				sprintf( 'for enum "%s". Cannot translate to corresponding case from given value: ["9"].', Collectable::class ),
				[ '9' ],
			],
			[
				NonCollectable::class,
				sprintf( 'because given enum "%s" does not have any case to use as value.', NonCollectable::class ),
			],
			[
				NonCollectable::class,
				// Throws for "3" coz computed in reverse order.
				sprintf( 'for enum "%s". Cannot translate to corresponding case from given value: ["3"].', NonCollectable::class ),
				[ '1', null, '3' ],
			],
			[
				Collectable::class,
				sprintf( 'during computation with enum "%s". All given arguments are "null" and none of them are valid value.', Collectable::class ),
				[ null, null ],
			],
		];
	}

	/**
	 * @param list<string|BackedEnum<string>>      $recomputeCases
	 * @param list<null|string|BackedEnum<string>> $cases
	 */
	#[Test]
	#[DataProvider( 'provideCasesThatThrowsExceptionForRecompute' )]
	public function itThrowsExceptionWhenStringCannotResolveEnumCaseWhenInvalidSubsetProvidedForRecomputation(
		array $recomputeCases,
		string $reason,
		array $cases = []
	): void {
		$this->expectExceptionMessage( $reason );

		( new CollectUsing( Collectable::class, null, ...$cases ) )->recomputationOf( ...$recomputeCases );
	}

	/** @return mixed[] */
	public static function provideCasesThatThrowsExceptionForRecompute(): array {
		return [
			[
				[ '9', '1' ],
				sprintf( 'for enum "%s". Cannot translate to corresponding case from given value: ["9"].', Collectable::class ),
			],
			[
				[ '0', '4' ],
				sprintf( 'during re-computation with enum "%s". Allowed values: ["1", "3"]. Given values: ["0", "4"].', Collectable::class ),
				[ null, '1', null, '3' ],
			],
			[
				[ '0' ],
				sprintf( 'during re-computation with enum "%s". Allowed value: ["1"]. Given value: ["0"].', Collectable::class ),
				[ null, '1' ],
			],
			[
				[ '0', '2' ],
				sprintf( 'during re-computation with enum "%s". Allowed value: ["1"]. Given values: ["0", "2"].', Collectable::class ),
				[ '1', null ],
			],
			[
				[ '0' ],
				sprintf( 'during re-computation with enum "%s". Allowed values: ["1", "2"]. Given value: ["0"].', Collectable::class ),
				[ null, '1', '2' ],
			],
		];
	}

	/**
	 * @param array{0:non-empty-list<?string>,1:?string,2:bool}                          $args
	 * @param array{0:list<?string>,1:non-empty-array<int,string>,2:list<int>,3?:string} $expected
	 */
	#[Test]
	#[DataProvider( 'provideCollectableItemsAsArray' )]
	public function itInstantiatesStaticallyUsingCollectableItemsArray( array $args, array $expected ): void {
		if ( $exceptionMsg = ( $expected[3] ?? false ) ) {
			$this->expectExceptionMessage( $exceptionMsg );
		}

		[$items, $offsets, $indexKey] = $expected;
		$collection                   = CollectUsing::listOf( ...$args );

		$this->assertSame( $items, $collection->items );
		$this->assertSame( $offsets, $collection->offsets );
		$this->assertSame( $indexKey, $collection->indexKey );
		$this->assertFalse( isset( $collection->enumClass ) );
	}

	/** @return mixed[] */
	public static function provideCollectableItemsAsArray(): array {
		// phpcs:disable WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		return [
			[
				[ [ '1', '2', null, '3' ], null, true ],
				[ [ 0 => '1', 1 => '2', 3 => '3' ], [ 2 ], null ],
			],
			[
				[ [ '1', '2', null, '3' ], '1', true ],
				[ [ 0 => '1', 1 => '2', 3 => '3' ], [ 2 ], '1' ],
			],
			[
				[ [ '1', '2', '3' ], '2', false ],
				[ [ '1', '2', '3' ], [], '2' ],
			],
			[
				[ [ '1', '2', '3' ], '2', true ],
				[ [ '1', '2', '3' ], [], '2' ],
			],
			[
				[ [ '1', '2', '3' ], null, true ],
				[ [ '1', '2', '3' ], [], null ],
			],
			[
				[ [ '1', '2', '3' ], null, false ],
				[ [ '1', '2', '3' ], [], null ],
			],
			[
				[ [ '1', null, '9' ], null, false ],
				[ 'exception->', 'is->', 'thrown->', 'because when computation is disabled, "null" (offset) must not be passed as values: ["1", "{{NULL}}", "9"].' ],
			],
			[
				[ [ '1', null, '9' ], '2', false ],
				[ 'exception->', 'is->', 'thrown->', 'because index-key must be one of the value in the given list. "2" does not exist in list of values: ["1", "{{NULL}}", "9"].' ],
			],
			[
				[ [ '1', null ], '2', true ],
				[ 'exception->', 'is->', 'thrown->', 'because index-key must be one of the value in the given list. "2" does not exist in list of values: ["1", "{{NULL}}"].' ],
			],
		];
		// phpcs:enable
	}

	#[Test]
	public function itRecomputesStaticallyInstantiatedCollection(): void {
		$collection   = CollectUsing::listOf( [ '1',null,'2',null,'3' ], '3', true );
		$reCollection = $collection->subsetOf( '2', '1' );

		$this->assertFalse( isset( $reCollection->enumClass ) );
		$this->assertSame( [ 1 ], $reCollection->offsets );
		$this->assertSame( '3', $reCollection->indexKey );
		$this->assertSame(
			[
				0 => '1',
				2 => '2',
			],
			$reCollection->items
		);
	}

	/**
	 * @param non-empty-list<?string> $names
	 * @param list<string>            $subsetNames
	 */
	#[Test]
	#[DataProvider( 'provideCasesThatThrowsExceptionForRecomputeFromArray' )]
	public function itThrowsExceptionWhenStaticallyInstantiatedOrIsRecomputed(
		array $names,
		array $subsetNames,
		string $expectedMsg
	): void {
		$this->expectExceptionMessage( $expectedMsg );

		CollectUsing::listOf( $names, compute: true )->subsetOf( ...$subsetNames );
	}

	/** @return mixed[] */
	public static function provideCasesThatThrowsExceptionForRecomputeFromArray(): array {
		return [
			[ [ null, null ], [ 'throws exception->' ], 'during computation. All given arguments are "null" and none of them are valid value.' ],
			[ [ '1', '2' ], [ '3', '4' ], 'during re-computation. Allowed values: ["1", "2"]. Given values: ["3", "4"]' ],
			[ [ '1' ], [ '3' ], 'during re-computation. Allowed value: ["1"]. Given value: ["3"]' ],
			[ [ '1', '2' ], [ '3' ], 'during re-computation. Allowed values: ["1", "2"]. Given value: ["3"]' ],
			[ [ '1' ], [ '3', '4' ], 'during re-computation. Allowed value: ["1"]. Given values: ["3", "4"]' ],
			[ [], [ 'throws exceptions->' ], 'because given list is empty. Provide at-least one value.' ],
		];
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

/** @template-implements BackedEnum<string> */
enum Collectable: string {
	case Zero  = '0';
	case One   = '1';
	case Two   = '2';
	case Three = '3';
	case Four  = '4';
}

/** @template-implements BackedEnum<string> */
enum NonCollectable: string {}
