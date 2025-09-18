<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits;

use TheWebSolver\Codegarage\Scraper\Enums\AccentedChars;

trait Diacritic {
	private AccentedChars $accentedCharacterAction;

	public function setAccentOperationType( AccentedChars $action ): void {
		$this->accentedCharacterAction = $action;
	}

	public function getAccentOperationType(): ?AccentedChars {
		return $this->accentedCharacterAction ?? null;
	}

	public function getDiacriticsList(): array {
		return AccentedChars::DIACRITIC_MAP;
	}
}
