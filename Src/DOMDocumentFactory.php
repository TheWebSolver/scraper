<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper;

use DOMElement;
use DOMDocument;
use TheWebSolver\Codegarage\Scraper\Helper\Normalize;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;

class DOMDocumentFactory {
	/** @throws InvalidSource When cannot load HTML content to the DOM Document. */
	public static function createFromHtml(
		string $contentOrFile,
		bool $noImpliedHtmlBody = true,
		bool $normalize = true
	): DOMDocument {
		$options                        = LIBXML_NOERROR | LIBXML_NOBLANKS;
		$noImpliedHtmlBody && $options |= LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED;
		$dom                            = new DOMDocument();
		$dom->formatOutput              = false;
		$source                         = is_readable( $contentOrFile )
			? ( file_get_contents( $contentOrFile ) ?: throw InvalidSource::contentNotFound( $contentOrFile ) )
			: $contentOrFile;

		$dom->loadHTML( $normalize ? Normalize::controlsAndWhitespacesIn( $source ) : $source, $options )
			?: throw InvalidSource::nonLoadableContent();

		return $dom;
	}

	public static function bodyFromHtml( string $contentOrFile, bool $normalize = true ): DOMElement {
		return self::createFromHtml( $contentOrFile, noImpliedHtmlBody: false, normalize: $normalize )
			->getElementsByTagName( 'body' )
			->item( 0 ) ?? throw InvalidSource::nonLoadableContent();
	}
}
