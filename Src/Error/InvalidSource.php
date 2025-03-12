<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Error;

use ValueError;

class InvalidSource extends ValueError {
	public static function contentNotFound( string $path ): self {
		return new self( sprintf( 'Impossible to create DOM Document from given source path: "%s".', $path ) );
	}

	public static function nonLoadableContent(): self {
		return new self( 'Cannot load content to the DOM Document.' );
	}
}
