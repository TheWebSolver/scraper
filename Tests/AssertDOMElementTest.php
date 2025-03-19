<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use Iterator;
use DOMElement;
use DOMDocument;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\DataProvider;
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

	#[Test]
	#[DataProvider( 'provideHtmlString' )]
	public function itVerifiesWhetherGivenElementIsFound( bool $expected, string $html, string $type ): void {
		$this->dom->loadHTML( $html, LIBXML_NOERROR | LIBXML_NOBLANKS | LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED );

		/** @var Iterator */
		$iterator = $this->dom->childNodes->getIterator();

		$this->assertSame( $expected, AssertDOMElement::isNextIn( $iterator, $type ) );
	}

	/** @return mixed[] */
	public static function provideHtmlString(): array {
		return array(
			array( false, '<div></div>', 'ul' ),
			array( true, '<div></div>', 'div' ),
			array( false, '<div><!-- only comment --></div>', 'ul' ),
			array( true, '<!-- comment --><ul></ul>', 'ul' ),
		);
	}
}
