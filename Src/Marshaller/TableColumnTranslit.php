<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Marshaller;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Interfaces\IndexableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedCharacter;
use TheWebSolver\Codegarage\Scraper\Interfaces\IndexableTracerWithAccent;

/** @template-implements Transformer<IndexableTracerWithAccent,string> */
class TableColumnTranslit implements Transformer {
	/** @param Transformer<IndexableTracer,string> $base Base transformer which transforms column content. */
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

	private function skipTransliteration( IndexableTracerWithAccent $scope ): bool {
		$targetNames      = $scope->indicesWithAccentedCharacters() ?: $scope->getTracedItemsIndices();
		$name             = $scope->getCurrentTracedItemIndex();
		$isValidOperation = AccentedCharacter::ACTION_TRANSLIT === $scope->getAccentOperationType()
			&& ! empty( $scope->getDiacriticsList() );

		return ! $isValidOperation || ( $name && ! in_array( $name, $targetNames, strict: true ) );
	}
}
