<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Scraper\Traits\Diacritic;
use TheWebSolver\Codegarage\Scraper\Enums\AccentedChars;
use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedCharacter;

class AccentedCharacterTest extends TestCase {
	#[Test]
	public function itEnsuresDiacriticTraitWorks(): void {
		$handler = new class() implements AccentedCharacter {
			use Diacritic;
		};

		$this->assertNull( $handler->getAccentOperationType() );
		$this->assertSame( AccentedChars::DIACRITIC_MAP, $handler->getDiacriticsList() );

		$handler->setAccentOperationType( AccentedChars::Escape );

		$this->assertSame( AccentedChars::Escape, $handler->getAccentOperationType() );

		$handler->setAccentOperationType( AccentedChars::Translit );

		$this->assertSame( AccentedChars::Translit, $handler->getAccentOperationType() );
	}
}
