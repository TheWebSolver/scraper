<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Tracer;

use TheWebSolver\Codegarage\Scraper\Traits\Diacritic;
use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedIndexableItem;

/**
 * @template TInferredColumn
 * @template-extends TableTracer<TInferredColumn>
 */
abstract class AccentedTableTracer extends TableTracer implements AccentedIndexableItem {
	use Diacritic;

	/** @var list<string> */
	protected array $accentedItemIndices = [];

	public function indicesWithAccentedCharacters(): array {
		return $this->accentedItemIndices;
	}
}
