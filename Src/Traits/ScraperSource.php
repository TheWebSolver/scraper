<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits;

use ReflectionClass;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;

trait ScraperSource {
	private ScrapeFrom $scraperSource;

	public function getSourceUrl(): string {
		return isset( $this->scraperSource ) ? $this->scraperSource->url : '';
	}

	protected function getScraperSource(): ScrapeFrom {
		return $this->scraperSource;
	}

	/** @param ReflectionClass<static> $reflection */
	final protected function sourceFromAttribute( ReflectionClass $reflection ): static {
		( $attribute = ( $reflection->getAttributes( ScrapeFrom::class )[0] ?? null ) )
			&& ( $this->scraperSource = $attribute->newInstance() );

		return $this;
	}
}
