<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Decorator;

use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Decorator\TranslitAccentedItem;
use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedIndexableItem;

/**
 * @template-extends TranslitAccentedItem<AccentedIndexableItem>
 * @template-implements Transformer<AccentedIndexableItem,string>
 */
class TranslitAccentedIndexableItem extends TranslitAccentedItem implements Transformer {
	protected function shouldTranslit( object $scope, array $diacriticList ): bool {
		if ( ! parent::shouldTranslit( $scope, $diacriticList ) ) {
			return false;
		}

		$targetNames = $scope->indicesWithAccentedCharacters() ?: $scope->getItemsIndices();
		$name        = $scope->getCurrentItemIndex() ?? throw new InvalidSource(
			sprintf( 'Accented item\'s index not found in scoped class: "%s".', $scope::class )
		);

		return in_array( $name, $targetNames, strict: true );
	}
}
