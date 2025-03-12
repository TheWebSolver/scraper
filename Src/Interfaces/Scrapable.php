<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use TheWebSolver\Codegarage\Scraper\ScraperError;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;

/**
 * @template TKey
 * @template TValue
 */
interface Scrapable {
	public const REMOVABLE_NODES     = array( "\n", "\t", "\r", "\v" );
	public const DIACRITICS_ESCAPE   = 1;
	public const DIACRITICS_TRANSLIT = 2;

	/**
	 * Scrapes content for the source.
	 *
	 * @throws ScraperError When cannot scrape the content.
	 */
	public function scrape(): string;

	/**
	 * Parses the scraped content.
	 *
	 * @param string $content The scraped content.
	 * @return array<TKey,TValue>
	 * @throws ScraperError When cannot parse the content.
	 */
	public function parse( string $content ): iterable;

	/**
	 * Caches the scraped content to a cache file.
	 *
	 * @return int Number of bytes written to the cache file.
	 * @throws ScraperError When caching fails.
	 */
	public function toCache( string $content ): int;

	/**
	 * Ensures whether scraped content has been cached to a file or not.
	 */
	public function hasCache(): bool;

	/**
	 * Gets scraped content from the cached file.
	 *
	 * @throws InvalidSource When cannot get content from cache file.
	 */
	public function fromCache(): string;

	/**
	 * Removes scraped content cache file, if any.
	 *
	 * @return bool `true` if cache is cleared, `false` otherwise.
	 */
	public function invalidateCache(): bool;

	/**
	 * Sets the cache path.
	 *
	 * @param string $dirPath  Absolute path to directory.
	 * @param string $filename The filename (with extension) to write data to.
	 * @throws InvalidSource When cannot find the directory path.
	 */
	public function withCachePath( string $dirPath, string $filename ): static;

	/**
	 * Sets the operation mode for accented characters.
	 *
	 * @param null|self::DIACRITICS* $operationType
	 */
	public function setDiacritic( ?int $operationType ): void;

	/**
	 * Sets keys to be used for collecting parsed data.
	 *
	 * @param string[] $keys     If keys are provided, the `Scrapable::parse()` must use
	 *                           only provided ones to collect data from parsed content.
	 * @param ?string  $indexKey One of the `$keys` whose value to be used as the key.
	 *                           If provided, it must be used as the collection key.
	 */
	public function useKeys( array $keys, ?string $indexKey = null ): void;

	/**
	 * Gets the resource URL from where content should be scraped.
	 */
	public function getSourceUrl(): string;

	/**
	 * Gets the cache file path.
	 */
	public function getCachePath(): string;

	/**
	 * Gets the operation mode for accented characters.
	 *
	 * @return null|self::DIACRITICS*
	 */
	public function getDiacritic(): ?int;

	/**
	 * Gets keys to be used for collecting parsed data.
	 *
	 * @return string[]
	 */
	public function getKeys(): array;

	/**
	 * Gets the index key used as the collection key of a set.
	 */
	public function getIndexKey(): ?string;

	/**
	 * Clears any garbage collected data during scraping.
	 */
	public function flush(): void;
}
