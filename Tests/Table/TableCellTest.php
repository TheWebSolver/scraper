<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Tests\Table;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Scraper\Data\TableCell;

class TableCellTest extends TestCase {
	#[Test]
	public function itIsADto(): void {
		$dto = new TableCell( 'value', 1, 0 );

		$this->assertFalse( $dto->shouldExtendToNextRow() );
		$this->assertTableCellValues( $dto, 'value', 1, 0 );

		foreach ( [ null, '' ] as $invalidValue ) {
			$this->assertFalse( ( new TableCell( $invalidValue, 2, 3 ) )->hasValidValue() );
		}
	}

	/** @param TableCell<covariant mixed> $cell */
	private function assertTableCellValues( TableCell $cell, mixed $value, int $rowspan, int $position ): void {
		foreach ( compact( 'value', 'rowspan', 'position' ) as $property => $value ) {
			$this->assertSame( $value, $cell->{$property} );
		}
	}

	#[Test]
	public function itReturnsNewInstanceWithUpdatedValues(): void {
		$dto = new TableCell( '1', 2, 3 );

		$this->assertTrue( $dto->shouldExtendToNextRow() );

		$withNewPosition = $dto->withPositionAt( 5 );

		$this->assertNotSame( $dto, $withNewPosition );
		$this->assertTableCellValues( $withNewPosition, '1', 2, 5 );

		$withNewRowSpan = $withNewPosition->withRemainingRowExtension( 2 );

		$this->assertNotSame( $withNewPosition, $withNewRowSpan );
		$this->assertTableCellValues( $withNewRowSpan, '1', 0, 5 );

		$this->assertFalse( $withNewRowSpan->shouldExtendToNextRow() );
	}
}
