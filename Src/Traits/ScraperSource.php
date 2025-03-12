<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits;

use ReflectionClass;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;

trait ScraperSource {
	private ScrapeFrom $scraperSource;
	private string $sourceUrl;

	public function getSourceUrl(): string {
		return $this->sourceUrl ?? '';
	}

	protected function getSource(): ScrapeFrom {
		return $this->scraperSource;
	}

	final protected function sourceFromAttribute( string $customUrl = '' ): static {
		if ( $this->scraperSource ?? null ) {
			return $this;
		}

		$attribute           = ( new ReflectionClass( static::class ) )->getAttributes( ScrapeFrom::class )[0];
		$this->scraperSource = $attribute->newInstance();
		$this->sourceUrl     = $customUrl ?: $this->scraperSource->url;

		return $this;
	}
}
