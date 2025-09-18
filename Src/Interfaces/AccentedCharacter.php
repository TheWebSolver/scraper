<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use TheWebSolver\Codegarage\Scraper\Enums\AccentedChars;

interface AccentedCharacter {
	/**
	 * Sets the action type for accented characters.
	 */
	public function setAccentOperationType( AccentedChars $action ): void;

	/**
	 * Gets the action type for accented characters.
	 */
	public function getAccentOperationType(): ?AccentedChars;

	/**
	 * Gets the list of accented characters with diacritic as key and replacement as value.
	 *
	 * @return array<string,string>
	 */
	public function getDiacriticsList(): array;
}
