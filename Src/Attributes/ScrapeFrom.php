<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Attributes;

use Attribute;

#[Attribute( flags: Attribute::TARGET_CLASS )]
final readonly class ScrapeFrom {
	public function __construct( public string $name, public string $url, public string $filename ) {}

	public function errorMsg(): string {
		return sprintf( 'Error occurred for source: "%1$s" when scaping from URL: "%2$s"', $this->name, $this->url );
	}
}
