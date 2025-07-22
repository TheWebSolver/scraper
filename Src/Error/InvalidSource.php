<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Error;

use ValueError;
use TheWebSolver\Codegarage\Scraper\Attributes\CollectUsing;

class InvalidSource extends ValueError {
	public static function contentNotFound( string $path ): self {
		return new self( sprintf( 'Impossible to create DOM Document from given source path: "%s".', $path ) );
	}

	public static function nonLoadableContent(): self {
		return new self( 'Cannot load content to the DOM Document.' );
	}

	public static function nonCollectableItem( string $name ): self {
		return new self(
			sprintf( '"%1%s" cannot collect data using invalid enum case value "%2$s".', CollectUsing::class, $name )
		);
	}
}
