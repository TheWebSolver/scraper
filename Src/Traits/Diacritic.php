<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits;

use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedCharacter;

trait Diacritic {
	/** @var AccentedCharacter::ACTION_* */
	private int $accentedCharacterAction;

	public function setAccentOperationType( int $action ): void {
		$this->accentedCharacterAction = $action;
	}

	public function getAccentOperationType(): ?int {
		return $this->accentedCharacterAction ?? null;
	}

	public function getDiacriticsList(): array {
		return AccentedCharacter::DIACRITIC_MAP;
	}
}
