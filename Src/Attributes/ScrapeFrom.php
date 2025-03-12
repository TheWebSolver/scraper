<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Attributes;

use Attribute;
use TheWebSolver\Codegarage\Scraper\Interfaces\ScraperEnum;

#[Attribute( flags: Attribute::TARGET_CLASS )]
final readonly class ScrapeFrom {
	/** @param class-string<ScraperEnum> $scraperEnum */
	public function __construct(
		public string $url,
		public string $filename,
		public string $scraperEnum,
		public string $name
	) {}
}
