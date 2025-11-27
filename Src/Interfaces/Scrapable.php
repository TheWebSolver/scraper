<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use Iterator;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;

/** @template ScrapedKeyValue of Iterator */
interface Scrapable {
	/**
	 * Scrapes content from the source.
	 *
	 * @throws ScraperError When cannot scrape the content.
	 */
	public function scrape(): string;

	/**
	 * Parses scraped content.
	 *
	 * @return ScrapedKeyValue
	 * @throws InvalidSource When cannot infer expected type from the content.
	 * @throws ScraperError  When cannot parse the content.
	 */
	public function parse(): Iterator;

	/**
	 * Caches scraped content to the cache file.
	 *
	 * @return int Number of bytes written to the cache file.
	 * @throws ScraperError When caching fails.
	 */
	public function toCache( string $content ): int;

	/**
	 * Ensures whether scraped content has been cached to the file or not.
	 */
	public function hasCache(): bool;

	/**
	 * Gets scraped content from the cached file.
	 *
	 * @throws InvalidSource When cannot get content from the cache file.
	 */
	public function fromCache(): string;

	/**
	 * Deletes the cache file, if exists.
	 *
	 * @return bool `true` if the cache file is deleted, else `false`.
	 */
	public function invalidateCache(): bool;

	/**
	 * Sets the cache file path.
	 *
	 * @param string $dirPath  Absolute path to directory.
	 * @param string $filename The filename (with extension) to write content to.
	 * @throws InvalidSource When directory path could not be located.
	 */
	public function withCachePath( string $dirPath, string $filename ): static;

	/**
	 * Gets the resource URL from where content should be scraped.
	 */
	public function getSourceUrl(): string;

	/**
	 * Gets absolute path to the cache filename (with extension).
	 */
	public function getCachePath(): string;

	/**
	 * Clears any garbage collected data during scraping.
	 */
	public function flush(): void;
}
