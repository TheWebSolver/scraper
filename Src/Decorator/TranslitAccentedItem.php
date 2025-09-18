<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Decorator;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Enums\AccentedChars;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedCharacter;

/**
 * @template TScope of AccentedCharacter
 * @template-implements Transformer<TScope,string>
 */
class TranslitAccentedItem implements Transformer {
	/** @param Transformer<contravariant TScope,string> $base */
	public function __construct( private readonly Transformer $base ) {}

	public function transform( string|array|DOMElement $element, object $scope ): string {
		$characters = $scope->getDiacriticsList();

		if ( ! $this->shouldTranslit( $scope, $characters ) ) {
			return $this->base->transform( $element, $scope );
		}

		return ( $content = $this->base->transform( $element, $scope ) )
			? str_replace( array_keys( $characters ), array_values( $characters ), $content )
			: $content;
	}

	/**
	 * @param array<string,string> $characters
	 * @param TScope               $scope
	 */
	protected function shouldTranslit( object $scope, array $characters ): bool {
		return ( AccentedChars::Translit === $scope->getAccentOperationType() ) && ! empty( $characters );
	}
}
