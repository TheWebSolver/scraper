<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Proxy;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Error\ValidationFail;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Validatable;
use TheWebSolver\Codegarage\Scraper\Marshaller\MarshallItem;
use TheWebSolver\Codegarage\Scraper\Marshaller\HtmlEntityDecode;
use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedIndexableTracer;
use TheWebSolver\Codegarage\Scraper\Marshaller\TranslitAccentedIndexableItem;

/** @template-implements Transformer<Validatable<string>&AccentedIndexableTracer,string> */
class ItemValidatorProxy implements Transformer {
	/** @var Transformer<contravariant AccentedIndexableTracer,string> */
	private Transformer $base;

	/** @throws ValidationFail When transformed data is invalid. */
	public function transform( string|array|DOMElement $element, object $scope ): string {
		$this->hydrateBaseTransformer( $element );

		$value = $this->base->transform( $element, $scope );

		$value && $scope->validate( $value );

		return $value;
	}

	private function hydrateBaseTransformer( mixed $element ): void {
		if ( $this->base ?? false ) {
			return;
		}

		$base = new MarshallItem();

		$element instanceof DOMElement || $base = new HtmlEntityDecode( $base );

		$this->base = new TranslitAccentedIndexableItem( $base );
	}
}
