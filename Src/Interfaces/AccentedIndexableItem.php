<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

interface AccentedIndexableItem extends Indexable, AccentedCharacter {
	/**
	 * Gets iterable items' indices which contains accented characters.
	 *
	 * This must return all or subset of `Indexable::getIndicesSource()`.
	 *
	 * @return list<string>
	 */
	public function indicesWithAccentedCharacters(): array;
}
