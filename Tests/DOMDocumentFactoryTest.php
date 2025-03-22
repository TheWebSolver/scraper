<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Scraper\DOMDocumentFactory;

class DOMDocumentFactoryTest extends TestCase {
	final public const RESOURCE_PATH = __DIR__ . DIRECTORY_SEPARATOR . 'Resource' . DIRECTORY_SEPARATOR;

	#[Test]
	public function itCreateDomDocumentBasedOnHTMLContent(): void {
		$content = <<<CONTENT_WITHOUT_HTML_TAG
		<div class="test">Some content</div>
		CONTENT_WITHOUT_HTML_TAG;

		$this->assertSame(
			'div',
			DOMDocumentFactory::createFromHtml( $content, normalize: false )->firstChild?->nodeName,
			'Does not create <html> & <body> node automatically if source does not have those specific tags'
		);

		$dom = DOMDocumentFactory::createFromHtml( $content, noImpliedHtmlBody: false, normalize: false );

		$this->assertCount( 2, $dom->childNodes );
		$this->assertSame(
			XML_DOCUMENT_TYPE_NODE,
			$dom->firstChild?->nodeType,
			'Creates <!DOCTYPE>, <html> and <body> elements automatically $noImpliedHtmlBody is "false"'
		);

		$content = <<<CONTENT_WITH_HTML_TAG
		<html>
			<body>
				<div class="test">Some content</div>
			</body>
		</html>
		CONTENT_WITH_HTML_TAG;

		$this->assertSame(
			XML_ELEMENT_NODE,
			DOMDocumentFactory::createFromHtml( $content, normalize: false )->firstChild?->nodeType,
			'Does not create <!DOCTYPE> node automatically if source does not have that specific tag'
		);

		$this->assertSame(
			XML_DOCUMENT_TYPE_NODE,
			DOMDocumentFactory::createFromHtml( $content, noImpliedHtmlBody: false, normalize: false )->firstChild?->nodeType,
			'Creates <!DOCTYPE> node automatically $noImpliedHtmlBody is "false"'
		);

		$partialContent = '
			<!-- first node is comment -->
			<nav>Navigation</nav>
			<div>Content</div>
		';

		// Captures all three nodes when first node is comment.
		$this->assertCount( 3, DOMDocumentFactory::createFromHtml( $partialContent )->childNodes );

		$partialContent = '
			<nav>Navigation</nav>
			<!-- first node is <nav> element -->
			<div>Breadcrumbs</div>
			<!-- another comment -->
			<main>Main content</main>
		';

		// Only captures first node and all comment nodes when first node is not comment.
		// This is how PHP DOMDocument works and is not limitation of DOMDocumentFactory.
		$this->assertCount( 3, DOMDocumentFactory::createFromHtml( $partialContent )->childNodes );
	}

	#[Test]
	public function itCreateDomDocumentBasedOnHTMLfilepath(): void {
		$dom = DOMDocumentFactory::createFromHtml( self::RESOURCE_PATH . 'partial-content.html' );

		$this->assertSame( 'nav', $dom->firstChild?->nodeName );

		$dom = DOMDocumentFactory::createFromHtml( self::RESOURCE_PATH . 'full-content.html' );

		$this->assertSame( XML_DOCUMENT_TYPE_NODE, $dom->firstChild?->nodeType );
		$this->assertSame( XML_ELEMENT_NODE, $dom->lastChild?->nodeType );
	}

	#[Test]
	public function itReturnsHtmlBodyElement(): void {
		$fullBody    = DOMDocumentFactory::bodyFromHtml( self::RESOURCE_PATH . 'full-content.html' );
		$partialBody = DOMDocumentFactory::bodyFromHtml( self::RESOURCE_PATH . 'partial-content.html' );

		$this->assertSame( 2, $fullBody->childNodes->length, 'Includes text node & div element' );
		$this->assertSame( 1, $fullBody->childElementCount, 'Includes only div element' );
		$this->assertSame( 3, $partialBody->childElementCount, 'Includes one nav & two divs' );
		$this->assertSame( 5, $partialBody->childNodes->length, 'Includes one nav, two comments & two divs' );
	}
}
