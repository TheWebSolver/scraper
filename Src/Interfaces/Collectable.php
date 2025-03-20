<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use Closure;
use BackedEnum;

interface Collectable extends BackedEnum {
	/** @placeholder: **1:** expected number of items, **2:** expected item names (usually enum case values). */
	public const INVALID_COUNT_MESSAGE = 'parsed data invalid. Collection must have atleast "%1$s" items: "%2$s".';

	/**
	 * Gets character length of the scraped data parsed.
	 */
	public function length(): int;

	/**
	 * Ensures scraped data's character type and length is valid.
	 */
	public function isCharacterTypeAndLength( string $value ): bool;

	/**
	 * Gets the error message if scraped data is not of expected character type or length.
	 */
	public function errorMsg(): string;

	/**
	 * Explains the type of enum.
	 */
	public static function type(): string;

	/**
	 * Gets the error message if scraped data's collection set (items) does not match the expected length.
	 *
	 * Error message must support following placeholders:
	 * - **1:** expected number of items, and
	 * - **2:** expected item names (usually enum case values).
	 */
	public static function invalidCountMsg(): string;

	/**
	 * Gets the key/value pair of a backed enum case name and its value.
	 *
	 * @param BackedEnum ...$filter The enum case(s) to filter from being included as an array.
	 * @return array<string,string>
	 */
	public static function toArray( BackedEnum ...$filter ): array;

	/**
	 * Walks a given data and its index key to handle if verification fails.
	 *
	 * @param string                    $data    The value to verify.
	 * @param string                    $key     One of the backed enum case value.
	 * @param Closure(self): bool|never $handler Accepts enum instance. It is only invoked if data cannot be verified.
	 */
	public static function walkForTypeVerification( string $data, string $key, Closure $handler ): bool;
}
