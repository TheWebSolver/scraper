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
		$element    = new DOMElement(
			'div',
			'
				This is a div content.  '
		);
		$collection = $marshaller->transform( $element, 0 );

		$this->assertSame( 'This is a div content.', $collection );
	}
}
