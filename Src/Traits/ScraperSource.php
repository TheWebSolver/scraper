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

	/** @param ?ReflectionClass<static> $reflection */
	final protected function sourceFromAttribute( ?ReflectionClass $reflection = null ): static {
		if ( $this->scraperSource ?? null ) {
			return $this;
		}

		$reflection        ??= new ReflectionClass( static::class );
		$attribute           = $reflection->getAttributes( ScrapeFrom::class )[0];
		$this->scraperSource = $attribute->newInstance();
		$this->sourceUrl     = $this->scraperSource->url;

		return $this;
	}
}
