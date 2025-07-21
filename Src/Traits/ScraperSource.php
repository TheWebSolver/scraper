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

	/**
	 * Registers scraper source via the class attribute.
	 *
	 * However, this will never override an already existing scraper source instance.
	 * Usually, this might happen with constructor injection & using setter method.
	 *
	 * @see ScraperSource::setScraperSource()
	 */
	final protected function sourceFromAttribute(): static {
		if ( $this->scraperSource ?? false ) {
			return $this;
		}

		$attribute = ( new ReflectionClass( static::class ) )->getAttributes( ScrapeFrom::class )[0] ?? null;

		$attribute && ( $this->scraperSource = $attribute->newInstance() );

		return $this;
	}

	final protected function setScraperSource( ScrapeFrom $source ): void {
		$this->scraperSource = $source;
	}
}
