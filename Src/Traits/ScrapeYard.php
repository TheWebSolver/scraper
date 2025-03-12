<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits;

use TheWebSolver\Codegarage\Scraper\ScraperError;
use TheWebSolver\Codegarage\Scraper\Traits\CachePath;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Interfaces\Scrapable;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;

trait ScrapeYard {
	use CachePath;

	/** @var null|Scrapable::DIACRITICS* */
	private ?int $diacriticOperationType = null;
	/** @var string[] */
	private array $collectionKeys = array();
	private ?string $indexKey     = null;

	abstract public function getSourceUrl(): string;
	abstract protected function getSource(): ScrapeFrom;
	/** @return array<string,string> */
	abstract protected function getDiacritics(): array;

	public function useKeys( array $keys, ?string $indexKey = null ): void {
		$this->collectionKeys = $keys;

		! is_null( $indexKey ) && $this->indexKey = $indexKey;
	}

	public function getKeys(): array {
		return $this->collectionKeys;
	}

	public function getIndexKey(): ?string {
		return $this->indexKey;
	}

	public function hasCache(): bool {
		return is_readable( $this->getCachePath() );
	}

	public function clearCache(): bool {
		return $this->hasCache() && unlink( $this->getCachePath() );
	}

	public function setDiacritic( ?int $operationType ): void {
		$this->diacriticOperationType = $operationType;
	}

	public function getDiacritic(): ?int {
		return $this->diacriticOperationType;
	}

	public function scrape(): string {
		return file_get_contents( $url = $this->getSourceUrl() ) ?: $this->notFound( $url );
	}

	public function fromCache(): string {
		return file_get_contents( $path = $this->getCachePath() ) ?: $this->notFound( $path );
	}

	public function toCache( string $content ): int {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return @file_put_contents( $this->getCachePath(), $content )
			?: throw ScraperError::trigger(
				'Could not cache scraped content from %1$s source: %2$s.%3$s',
				$this->getSource()->name,
				$this->getSource()->url,
				( $e = error_get_last() ) ? "Reason: {$e['message']}." : ''
			);
	}

	protected function maybeTranslit( string $text ): string {
		return Scrapable::DIACRITICS_TRANSLIT !== $this->getDiacritic() ? $text : str_replace(
			search: array_keys( $this->getDiacritics() ),
			replace: array_values( $this->getDiacritics() ),
			subject: $text
		);
	}

	/**
	 * @param array<string,TValue> $collected Full data collected after parsing.
	 * @return array<string,TValue>
	 * @template TValue
	 */
	private function withRequestedKeys( array $collected ): array {
		return array_intersect_key( $collected, array_flip( $this->getKeys() ) );
	}

	private function notFound( string $source ): never {
		throw new InvalidSource(
			sprintf( 'Could not fetch content from %1$s source: %2$s', $this->getSource()->name, $source )
		);
	}
}
