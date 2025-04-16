<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

interface AccentedIndexableItem extends Indexable, AccentedCharacter {
	/**
	 * Gets iterable items' indices which contains accented characters.
	 *
	 * This must return all or subset of `Indexable::getItemsIndices()`. If indices not set,
	 * then this must return an integer position (starting from **0**) value of the targeted item.
	 *
	 * @return list<string|int>
	 */
	public function indicesWithAccentedCharacters(): array;
}
