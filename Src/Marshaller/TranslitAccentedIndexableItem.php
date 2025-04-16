<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Marshaller;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedIndexableItem;

/** @template-implements Transformer<AccentedIndexableItem,string> */
class TranslitAccentedIndexableItem implements Transformer {
	/** @param Transformer<object,string> $base */
	public function __construct( private readonly Transformer $base ) {}

	public function transform( string|array|DOMElement $element, object $scope ): string {
		if ( $this->skipTransliteration( $scope ) ) {
			return $this->base->transform( $element, $scope );
		}

		if ( ! $content = $this->base->transform( $element, $scope ) ) {
			return $content;
		}

		$characters = $scope->getDiacriticsList();

		return str_replace( array_keys( $characters ), array_values( $characters ), $content );
	}

	private function skipTransliteration( AccentedIndexableItem $scope ): bool {
		$targetNames    = $scope->indicesWithAccentedCharacters() ?: $scope->getItemsIndices();
		$name           = $scope->getCurrentItemIndex();
		$shouldTranslit = $scope::ACTION_TRANSLIT === $scope->getAccentOperationType()
			&& ! empty( $scope->getDiacriticsList() );

		return ! $shouldTranslit || ( $name && ! in_array( $name, $targetNames, strict: true ) );
	}
}
