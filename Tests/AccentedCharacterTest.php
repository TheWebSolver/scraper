<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Scraper\Traits\Diacritic;
use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedCharacter;

class AccentedCharacterTest extends TestCase {
	#[Test]
	public function itEnsuresDiacriticTraitWorks(): void {
		$handler = new class() implements AccentedCharacter {
			use Diacritic;
		};

		$this->assertNull( $handler->getAccentOperationType() );
		$this->assertSame( AccentedCharacter::DIACRITIC_MAP, $handler->getDiacriticsList() );

		$handler->setAccentOperationType( AccentedCharacter::ACTION_ESCAPE );

		$this->assertSame( AccentedCharacter::ACTION_ESCAPE, $handler->getAccentOperationType() );

		$handler->setAccentOperationType( AccentedCharacter::ACTION_TRANSLIT );

		$this->assertSame( AccentedCharacter::ACTION_TRANSLIT, $handler->getAccentOperationType() );
	}
}
