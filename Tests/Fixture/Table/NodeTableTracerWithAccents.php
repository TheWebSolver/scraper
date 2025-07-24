<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Fixture\Table;

use TheWebSolver\Codegarage\Scraper\Traits\Diacritic;
use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedIndexableItem;

class NodeTableTracerWithAccents extends NodeTableTracer implements AccentedIndexableItem {
	use Diacritic;

	/** @param list<string> $accentedItemIndices */
	public function __construct( protected array $accentedItemIndices = [] ) {
		parent::__construct();
	}

	public function indicesWithAccentedCharacters(): array {
		return $this->accentedItemIndices;
	}
}
