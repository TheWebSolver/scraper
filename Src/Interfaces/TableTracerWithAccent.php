<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

/**
 * @template TColumnReturn
 * @template-extends TableTracer<TColumnReturn>
 */
interface TableTracerWithAccent extends TableTracer, AccentedCharacter {
	/**
	 * Gets table columns which contains accented characters.
	 *
	 * This must return all or subset of `TableTracer::getColumnNames()`. If there are no column(s),
	 * then this must return column's position (starting from **0**) of the targeted column(s).
	 *
	 * @return list<string|int>
	 */
	public function columnsWithAccentedCharacters(): array;
}
