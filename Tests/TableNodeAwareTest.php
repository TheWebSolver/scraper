<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use DOMElement;
use ArrayObject;
use DOMDocument;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Scraper\Helper\Normalize;
use TheWebSolver\Codegarage\Scraper\Helper\Marshaller;
use TheWebSolver\Codegarage\Scraper\Traits\TableNodeAware;

class TableNodeAwareTest extends TestCase {
	#[Test]
	public function itGetsTheTargetedTableNode(): void {
		$dom     = new DOMDocument();
		$handler = new class() {
			use TableNodeAware;

			protected function isTargetedTable( DOMElement $node ): bool {
				if ( ! ( $firstChild = $node->firstElementChild ) || 'caption' !== $firstChild->tagName ) {
					return false;
				}

				$value        = $firstChild->nodeValue;
				$captionValue = $value ? Normalize::nonBreakingSpaceToWhitespace( $value ) : '';

				if ( ! $captionValue || ! str_starts_with( trim( $captionValue ), 'Active ISO 4217 currency codes' ) ) {
					return false;
				}

				return true;
			}
		};

		$resource          = __DIR__ . '/Resource/wiki-iso-4217.html';
		$dom->formatOutput = false;

		$this->assertTrue( $dom->loadHTMLFile( $resource, LIBXML_NOERROR | LIBXML_NOBLANKS ) );

		$thMarshaller = function ( string|DOMElement $e ) {
			return explode( '[', $e instanceof DOMElement ? $e->textContent : $e )[0];
		};

		$handler
			->useMarshaller(
				( new Marshaller( 'th' ) )->marshallWith( $thMarshaller ),
				( new Marshaller( 'td' ) )
			)->withAllTableNodes()
			->withOnlyContents()
			->scanTableBodyNodeIn( $dom->childNodes );

		$this->assertCount( 1, $handler->getTableIds() );

		$data  = $handler->getTableData();
		$first = $data[ $tableId = $handler->getTableIds()[0] ];

		$this->assertInstanceOf( ArrayObject::class, $first );
		$this->assertEqualsCanonicalizing(
			array_keys( $first->getArrayCopy() ),
			$handler->getTableHead( true )[ $tableId ]->toArray()
		);
	}
}
