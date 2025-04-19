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

		foreach ( [ 'one', 'two', 'three' ] as $class ) {
			$this->assertTrue( AssertDOMElement::hasClass( $element, $class ) );
		}
	}

	#[Test]
	#[DataProvider( 'provideHtmlString' )]
	public function itVerifiesWhetherGivenElementIsFound( ?string $expected, string $html, string $type ): void {
		$this->dom->loadHTML( $html, LIBXML_NOERROR | LIBXML_NOBLANKS );

		/** @var Iterator */
		$iterator = $this->dom->getElementsByTagName( 'body' )->item( 0 )?->childNodes->getIterator();

		$this->assertSame( $expected, AssertDOMElement::nextIn( $iterator, $type )?->tagName );
	}

	/** @return mixed[] */
	public static function provideHtmlString(): array {
		return [
			[ null, '<div></div>', 'ul' ],
			[ 'div', '<section></section><div></div><ul></ul>', 'div' ],
			[ null, '<div><!-- only comment --></div>', 'ul' ],
			[ 'ul', '<!-- comment --><div></div><ul></ul>', 'ul' ],
		];
	}

	#[Test]
	#[DataProvider( 'provideStringToInferElementByType' )]
	public function itInfersDOMElementByItsTagName( string $toInfer, string $type, ?string $expected ): void {
		$element = AssertDOMElement::inferredFrom( $toInfer, $type, normalize: false );

		$this->assertSame( $expected, $element?->textContent );
	}

	/** @return mixed[] */
	public static function provideStringToInferElementByType(): array {
		return [
			[ 'content <span>skip</span><!--skip--><div>div value</div>', 'div', 'div value' ],
			[ '<span>skip</span><!-- NO <pre> TAG HERE --><div></div>', 'pre', null ],
		];
	}
}
