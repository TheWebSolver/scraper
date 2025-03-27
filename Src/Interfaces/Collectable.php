<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use Closure;

interface Collectable {
	/** @placeholder: **1:** collection items count, **2:** stringified collection items. */
	public const INVALID_COUNT_MESSAGE = 'parsed data invalid. Collection must have atleast "%1$s" items: "%2$s".';

	/**
	 * Gets the error message if collection items's count does not match with collected data.
	 *
	 * Error message must support following placeholders:
	 * - **1:** collection items count, and
	 * - **2:** stringified collection items.
	 */
	public static function invalidCountMsg(): string;

	/**
	 * Provides details/scope about the collection.
	 */
	public static function label(): string;

	/**
	 * Gets collection items.
	 *
	 * @return list<string>
	 */
	public static function toArray(): array;

	/**
	 * Walks given data for validation.
	 *
	 * @param mixed                  $data The data to verify.
	 * @param string                 $item One of the item of collection set. It must be
	 *                                     used as an index value of the provided $data.
	 * @param Closure(string, string ...$args): bool|never $handler Must only be invoked if provided
	 *                                                              and data cannot be verified.
	 */
	public static function validate( mixed $data, string $item, ?Closure $handler = null ): bool;
}
