<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use DOMElement;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Scraper\Helper\Marshaller;

class MarshallerTest extends TestCase {
	#[Test]
	public function itCollectsContentOnly(): void {
		$marshaller = new Marshaller( 'div' );
		$element    = new DOMElement( 'div', 'This is a div content.' );
		$collection = $marshaller->onlyContent( false )->collect( $element );

		$this->assertSame( 'div', $marshaller->tagName );
		$this->assertSame(
			array(
				Marshaller::COLLECT_HTML         => false,
				Marshaller::COLLECT_NODE         => false,
				Marshaller::COLLECT_ONLY_CONTENT => false,
			),
			$marshaller->collectables()
		);

		$this->assertIsArray( $collection );
		$this->assertSame( 'This is a div content.', $collection[0] );

		$this->assertSame(
			'This is a div content.',
			$marshaller->onlyContent()->collect( $element ),
			'Only returns content as string when onlyContent is enabled'
		);

		$this->assertSame(
			array(
				Marshaller::COLLECT_HTML         => false,
				Marshaller::COLLECT_NODE         => false,
				Marshaller::COLLECT_ONLY_CONTENT => true,
			),
			$marshaller->collectables()
		);

		$this->assertSame( 'content', ( new Marshaller( '' ) )->onlyContent()->collect( 'content' ) );
	}

	#[Test]
	public function itCollectContentBasedOnCollectableData(): void {
		$marshaller = ( new Marshaller( 'div' ) )->collectHtml()->collectElement();
		$element    = new DOMElement( 'div', 'This is a div content.' );

		$this->assertSame(
			array(
				Marshaller::COLLECT_HTML         => true,
				Marshaller::COLLECT_NODE         => true,
				Marshaller::COLLECT_ONLY_CONTENT => false,
			),
			$marshaller->collectables()
		);

		$this->assertIsArray( $collection = $marshaller->collect( $element ) );
		$this->assertCount( 3, $collection );

		$this->assertSame(
			array(
				Marshaller::COLLECT_HTML         => true,
				Marshaller::COLLECT_NODE         => true,
				Marshaller::COLLECT_ONLY_CONTENT => true,
			),
			$marshaller->onlyContent()->collectables()
		);
	}

	#[Test]
	public function itOnlyCollectContentWhenCollectParamIsString(): void {
		$marshaller = ( new Marshaller( 'div' ) )->collectHtml()->collectElement();

		$this->assertIsArray( $collection = $marshaller->collect( 'This is div content.' ) );
		$this->assertCount( 1, $collection );
	}

	#[Test]
	public function itTransformsContentWhenMarshallerIsProvided(): void {
		$marshaller = ( new Marshaller( '' ) )->marshallWith(
			static fn( string|DOMElement $v ) => substr( is_string( $v ) ? $v : $v->textContent, 0, -1 )
		);

		$this->assertSame( array( 'onlyText' ), $marshaller->collect( 'onlyText.' ) );
	}

	#[Test]
	public function itEnsuresCollectedContentGetterAndResetterWorks(): void {
		$marshaller = new Marshaller( '' );

		$marshaller->collect( 'content' );

		$this->assertSame( array( 'content' ), $marshaller->content() );

		$marshaller->reset();

		$this->assertEmpty( $marshaller->content() );
	}
}
