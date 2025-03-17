<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper;

use DOMDocument;
use TheWebSolver\Codegarage\Scraper\Helper\Normalize;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;

class DOMDocumentFactory {
	/** @throws InvalidSource When cannot load HTML content to the DOM Document. */
	public static function createFromHtml( string $contentOrFile ): DOMDocument {
		$dom     = new DOMDocument();
		$options = LIBXML_NOERROR | LIBXML_NOBLANKS;
		$source  = is_readable( $contentOrFile )
			? ( file_get_contents( $contentOrFile ) ?: throw InvalidSource::contentNotFound( $contentOrFile ) )
			: $contentOrFile;

		self::maybeWithoutHtmlOrBodyElement( $source, $options );

		$dom->formatOutput = false;

		$dom->loadHTML( Normalize::controlsAndWhitespacesIn( $source ), $options )
			?: throw InvalidSource::nonLoadableContent();

		return $dom;
	}

	private static function maybeWithoutHtmlOrBodyElement( string $source, int &$options ): void {
		if ( ! str_contains( $source, '</html>' ) ) {
			$options |= LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED;
		}
	}
}
