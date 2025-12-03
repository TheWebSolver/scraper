<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Error;

use Closure;
use RuntimeException;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;

class ScraperError extends RuntimeException {
	/** @placeholder: `1:` Mismatch context, `2:` Pattern to apply to the subject, `2:` The subject. */
	final public const PATTERN_MISMATCH = '%1$s only supports defined pattern "%2$s". Cannot match pattern to given subject: "%3$s"';

	private static ?ScrapeFrom $source = null;

	/**
	 * Sets source globally for additional context when this error occurs.
	 * It allows to set scraper source for one time only. To set another
	 * source, user must unsubscribe previously set source beforehand
	 * by invoking the unsubscribe callback returned by this method.
	 *
	 * @return Closure Unsubscribe from the given source (cleanup call).
	 * @example usage
	 * ```php
	 * $unsubscribe = ScraperError::for($source);
	 * // ...perform task and throw error as required.
	 * $unsubscribe();
	 * $unsubscribe = ScraperError::for($anotherSource);
	 * ```
	 */
	public static function for( ScrapeFrom $source ): Closure {
		self::$source ??= $source;

		return static fn () => self::$source = null;
	}

	public static function getSource(): ?ScrapeFrom {
		return self::$source;
	}

	public static function trigger( string $msg, string|int ...$args ): self {
		return new self( sprintf( $msg, ...$args ) );
	}

	public static function withSourceMsg( string $msg, string|int ...$args ): never {
		$source = self::getSource();

		throw self::trigger( sprintf( $msg, ...$args ) . ( $source ? " {$source->errorMsg()}" : '' ) );
	}

	public static function patternMismatch( string $context, string $pattern, string $subject ): never {
		throw self::trigger( self::PATTERN_MISMATCH, $context, $pattern, $subject );
	}
}
