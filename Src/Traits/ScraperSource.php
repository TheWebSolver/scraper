<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits;

use ReflectionClass;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;

trait ScraperSource {
	/** @placeholders: **1:** Attribute classname, **2:** Exhibiting classname. */
	final public const SOURCE_NOT_DEFINED = 'Source not defined. Use attribute "%1$s" to define scraping source for class: "%2$s".';

	private ScrapeFrom $scraperSource;

	public function getSourceUrl(): string {
		return $this->getScraperSource()->url;
	}

	protected function getScraperSource(): ScrapeFrom {
		return $this->scraperSource ?? throw new InvalidSource(
			sprintf( self::SOURCE_NOT_DEFINED, ScrapeFrom::class, static::class )
		);
	}

	/** @param ReflectionClass<static> $reflection */
	final protected function sourceFromAttribute( ReflectionClass $reflection ): static {
		( $attribute = ( $reflection->getAttributes( ScrapeFrom::class )[0] ?? null ) )
			&& ( $this->scraperSource = $attribute->newInstance() );

		return $this;
	}
}
