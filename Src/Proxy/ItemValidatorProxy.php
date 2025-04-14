<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Proxy;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Error\ValidationFail;
use TheWebSolver\Codegarage\Scraper\Interfaces\{Transformer, Validatable, SingleTableScraperWithAccent};
use TheWebSolver\Codegarage\Scraper\Marshaller\{HtmlEntityDecode, TableColumnTranslit, TableColumnMarshaller};

/** @template-implements Transformer<Validatable<string>&SingleTableScraperWithAccent<string>,string> */
class ItemValidatorProxy implements Transformer {
	/** @var Transformer<contravariant SingleTableScraperWithAccent<string>,string> */
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

		$base = new TableColumnMarshaller();

		$element instanceof DOMElement || $base = new HtmlEntityDecode( $base );

		$this->base = new TableColumnTranslit( $base );
	}
}
