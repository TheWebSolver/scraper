<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use BackedEnum;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Attributes\CollectUsing;

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
	 * @param list<null|string|BackedEnum<string>> $items
	 * @param list<string>                         $expectedItems
	 * @param list<int>                            $expectedOffsets
	 */
	#[Test]
	#[DataProvider( 'provideCollectableItems' )]
	public function itOffsetsInBetweenItems( array $items, array $expectedItems, array $expectedOffsets ): void {
		$collection = new CollectUsing( Collectable::class, null, ...$items );

		$this->assertSame( $expectedItems, $collection->items );
		$this->assertSame( $expectedOffsets, $collection->offsets );
	}

	/** @return mixed[] */
	public static function provideCollectableItems(): array {
		return [
			[ [], [ '0', '1', '2', '3', '4' ], [] ],
			[ [ Collectable::Zero, null, Collectable::Two ], [ '0', '2' ], [ 1 ] ],
			[ [ null, Collectable::One, Collectable::Two, null, Collectable::Four ], [ '1', '2', '4' ], [ 0, 3 ] ],
			[ [ Collectable::Four, null, null, Collectable::Zero, Collectable::Two ], [ '4', '0', '2' ], [ 1, 2 ] ],
			[ [ Collectable::Zero, Collectable::One, Collectable::Two ], [ '0', '1', '2' ], [] ],
		];
	}

	#[Test]
	public function itRecomputesOffsetsInBetweenItems(): void {
		$collection = new CollectUsing( Collectable::class );

		[$subsets, $offsets] = $collection->recomputeFor( '3', Collectable::Zero );

		$this->assertSame( [ '0', '3' ], $subsets );
		$this->assertSame( [ 1, 2 ], $offsets );

		$newCollection = $collection->with( '1', Collectable::Zero, '4' );

		$this->assertSame( Collectable::class, $newCollection->enumClass );
		$this->assertSame( [ '0', '1', '4' ], $newCollection->items );
		$this->assertSame( [ 2, 3 ], $newCollection->offsets );
		$this->assertNull( $newCollection->indexKey );
	}

	#[Test]
	public function itDoesNothingIfSubsetIsEmpty(): void {
		$collection    = new CollectUsing( Collectable::class );
		$newCollection = $collection->with();

		$this->assertSame( $collection, $newCollection );

		$collection->recomputeFor();

		$this->assertSame( [ '0', '1', '2', '3', '4' ], $collection->items );
		$this->assertSame( [], $collection->offsets );
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
