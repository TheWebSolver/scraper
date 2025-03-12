<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use DOMElement;
use DOMDocument;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Depends;
use TheWebSolver\Codegarage\Scraper\AssertDOMElement;

class AssertDOMElementTest extends TestCase {
	private DOMDocument $dom;

	protected function setUp(): void {
		$this->dom = new DOMDocument();
	}

	protected function tearDown(): void {
		unset( $this->dom );
	}

	#[Test]
	public function itEnsuresElementHasId(): DOMElement {
		$element = $this->dom->createElement( 'div' );

		$element->setAttribute( 'id', 'test' );

		$this->assertTrue( AssertDOMElement::hasId( $element, 'test' ) );

		return $element;
	}

	#[Test]
	#[Depends( 'itEnsuresElementHasId' )]
	public function itEnsuresElementHasClass( DOMElement $element ): void {
		$element->setAttribute( 'class', 'one two three' );

		foreach ( array( 'one', 'two', 'three' ) as $class ) {
			$this->assertTrue( AssertDOMElement::hasClass( $element, $class ) );
		}
	}
}
