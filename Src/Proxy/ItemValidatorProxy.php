<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Proxy;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Error\ValidationFail;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Validatable;
use TheWebSolver\Codegarage\Scraper\Marshaller\MarshallItem;
use TheWebSolver\Codegarage\Scraper\Decorator\HtmlEntityDecode;
use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedIndexableItem;
use TheWebSolver\Codegarage\Scraper\Decorator\TranslitAccentedIndexableItem;

/** @template-implements Transformer<AccentedIndexableItem&Validatable<string>,string> */
class ItemValidatorProxy implements Transformer {
	private bool $hydrated = false;
	/** @var Transformer<contravariant AccentedIndexableItem,string> */
	private Transformer $base;

	/** @param Transformer<contravariant AccentedIndexableItem,string> $base */
	public function __construct( ?Transformer $base = null ) {
		$base && $this->base = $base;
	}

	/** @throws ValidationFail When transformed data is invalid. */
	public function transform( string|array|DOMElement $element, object $scope ): string {
		$this->hydrateBaseTransformer( $element );

		$scope->validate( $transformed = $this->base->transform( $element, $scope ) );

		return $transformed;
	}

	private function hydrateBaseTransformer( mixed $element ): void {
		if ( $this->hydrated ) {
			return;
		}

		$this->hydrated = true;
		$base           = $this->base ?? new MarshallItem();
		$this->base     = new TranslitAccentedIndexableItem(
			$element instanceof DOMElement ? $base : new HtmlEntityDecode( $base )
		);
	}
}
