<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use DOMElement;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Scraper\Marshaller\Marshaller;

class MarshallerTest extends TestCase {
	#[Test]
	public function itCollectsContentOnly(): void {
		$marshaller = new Marshaller();
		$element    = new DOMElement( 'div', 'This is a div content.' );
		$collection = $marshaller->collect( $element );

		$this->assertSame(
			array(
				Marshaller::COLLECT_HTML => false,
				Marshaller::COLLECT_NODE => false,
			),
			$marshaller->collectables()
		);

		$this->assertSame( 'This is a div content.', $collection[0] );
		$this->assertSame(
			'This is a div content.',
			$marshaller->collect( $element, onlyContent: true ),
			'Only returns content as string when onlyContent is enabled'
		);

		$marshaller = new Marshaller();

		$this->assertSame( 'content', $marshaller->collect( 'content', onlyContent: true ) );
	}

	#[Test]
	public function itCollectContentBasedOnCollectableData(): void {
		$marshaller = new Marshaller();

		$marshaller->collectHtml();
		$marshaller->collectElement();

		$element = new DOMElement( 'div', 'This is a div content.' );

		$this->assertSame(
			array(
				Marshaller::COLLECT_HTML => true,
				Marshaller::COLLECT_NODE => true,
			),
			$marshaller->collectables()
		);

		$this->assertIsArray( $collection = $marshaller->collect( $element ) );
		$this->assertCount( 3, $collection );
	}

	#[Test]
	public function itOnlyCollectContentWhenCollectParamIsString(): void {
		$marshaller = new Marshaller();

		$marshaller->collectHtml();
		$marshaller->collectElement();

		$this->assertIsArray( $collection = $marshaller->collect( 'This is div content.' ) );
		$this->assertCount( 1, $collection );
	}

	#[Test]
	public function itTransformsContentWhenMarshallerIsProvided(): void {
		$marshaller = new Marshaller();

		$marshaller->marshallWith(
			static fn( string|DOMElement $v ) => substr( is_string( $v ) ? $v : $v->textContent, 0, -1 )
		);

		$this->assertSame( array( 'onlyText' ), $marshaller->collect( 'onlyText.' ) );
	}

	#[Test]
	public function itEnsuresCollectedContentGetterAndResetterWorks(): void {
		$marshaller = new Marshaller();

		$marshaller->collect( 'content' );

		$this->assertSame( array( 'content' ), $marshaller->getContent() );

		$marshaller->flushContent();

		$this->assertEmpty( $marshaller->getContent() );
	}
}
