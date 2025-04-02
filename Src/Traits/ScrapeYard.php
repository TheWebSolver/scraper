<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits;

use TheWebSolver\Codegarage\Scraper\Traits\CachePath;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;

trait ScrapeYard {
	use CachePath;

	abstract public function getSourceUrl(): string;
	abstract protected function getScraperSource(): ScrapeFrom;

	public function hasCache(): bool {
		return is_readable( $this->getCachePath() );
	}

	public function invalidateCache(): bool {
		return $this->hasCache() && unlink( $this->getCachePath() );
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
				$this->getScraperSource()->name,
				$this->getScraperSource()->url,
				( $e = error_get_last() ) ? "Reason: {$e['message']}." : ''
			);
	}

	private function notFound( string $source ): never {
		throw new InvalidSource(
			sprintf( 'Could not fetch content from %1$s source: %2$s', $this->getScraperSource()->name, $source )
		);
	}
}
