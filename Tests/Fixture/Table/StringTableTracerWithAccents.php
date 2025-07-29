<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Fixture\Table;

use TheWebSolver\Codegarage\Scraper\Traits\Diacritic;
use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedIndexableItem;

class StringTableTracerWithAccents extends StringTableTracer implements AccentedIndexableItem {
	use Diacritic;

	/** @var list<string> $accentedItemIndices */
	protected array $accentedItemIndices = [];

	public function indicesWithAccentedCharacters(): array {
		return $this->accentedItemIndices;
	}
}
