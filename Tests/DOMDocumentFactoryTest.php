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
			DOMDocumentFactory::createFromHtml( $content )->firstChild?->nodeName,
			'Must not create <html> node automatically if source does not have that specific tag'
		);

		$content = <<<CONTENT_WITH_HTML_TAG
		<html>
			<body>
				<div class="test">Some content</div>
			</body>
		</html>
		CONTENT_WITH_HTML_TAG;

		$this->assertSame( 'html', DOMDocumentFactory::createFromHtml( $content )->firstChild?->nodeName );
	}

	#[Test]
	public function itCreateDomDocumentBasedOnHTMLfilepath(): void {
		$dom = DOMDocumentFactory::createFromHtml( self::RESOURCE_PATH . 'partial-content.html' );

		$this->assertSame( 'div', $dom->firstChild?->nodeName );

		$dom = DOMDocumentFactory::createFromHtml( self::RESOURCE_PATH . 'full-content.html' );

		$this->assertSame( 'html', $dom->firstChild?->nodeName );
	}
}
