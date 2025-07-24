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

	/** @param string[] $names */
	public static function nonCollectableItem( array $names, string $reason = 'using invalid' ): self {
		return new self(
			sprintf(
				'"%1$s" cannot be used as collectable %2$s enum case value%3$s: ["%4$s"].',
				CollectUsing::class,
				$reason,
				count( $names ) === 1 ? '' : 's',
				implode( '", "', $names )
			)
		);
	}
}
