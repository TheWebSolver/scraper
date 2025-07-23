<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use BackedEnum;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Attributes\CollectUsing;

#[CoversClass( CollectUsing::class )]
class CollectUsingTest extends TestCase {
	#[Test]
	public function itConvertsEnumCasesToString(): void {
		$collection = new CollectUsing( Collectable::class, Collectable::One );

		$this->assertSame( Collectable::class, $collection->enumClass );
		$this->assertSame( [ '0', '1', '2', '3', '4' ], $collection->items );
		$this->assertSame( '1', $collection->indexKey );
		$this->assertSame( [], $collection->offsets );
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
		$newCollection = $collection->with( ...$recomputeSubsets );

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

	#[Test]
	public function itThrowsExceptionWhenStringCannotResolveEnumCaseWhenInvalidSubsetProvided(): void {
		$this->expectException( InvalidSource::class );

		new CollectUsing( Collectable::class, null, 'not-a-backed-enum-value' );
	}

	#[Test]
	public function itThrowsExceptionWhenStringCannotResolveEnumCaseWhenInvalidSubsetProvidedForRecomputation(): void {
		$this->expectException( InvalidSource::class );

		( new CollectUsing( Collectable::class ) )->recomputeFor( 'not-a-backed-enum-value' );
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
