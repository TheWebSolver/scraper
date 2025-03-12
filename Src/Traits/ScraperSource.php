<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits;

use BackedEnum;
use ReflectionClass;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;

trait ScraperSource {
	/** @var array<string,string> */
	protected array $columnNames;
	private ScrapeFrom $source;
	private string $sourceUrl;

	public function getSourceUrl(): string {
		return $this->sourceUrl ?? '';
	}

	protected function getSource(): ScrapeFrom {
		return $this->source;
	}

	/** @return array<string,string> */
	protected function getColumNames(): array {
		return $this->columnNames;
	}

	protected function setColumnNames(): void {
		$this->columnNames = $this->source->scraperEnum::toArray( ...$this->nonMappableCases() );
	}

	/**
	 * Allows exhibit to exclude non-mappable enum case(s) as column name.
	 *
	 * @return array<BackedEnum>
	 */
	protected function nonMappableCases(): array {
		return array();
	}

	final protected function sourceFromAttribute( string $customUrl = '' ): static {
		if ( $this->source ?? null ) {
			return $this;
		}

		$attribute       = ( new ReflectionClass( static::class ) )->getAttributes( ScrapeFrom::class )[0];
		$this->source    = $attribute->newInstance();
		$this->sourceUrl = $customUrl ?: $this->source->url;

		$this->setColumnNames();

		return $this;
	}
}
