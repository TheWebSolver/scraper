<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use Iterator;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
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
	 * @return Iterator<TKey,TValue>
	 * @throws ScraperError When cannot parse the content.
	 */
	public function parse( string $content ): Iterator;

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
	 * Clears any garbage collected data during scraping.
	 */
	public function flush(): void;
}
