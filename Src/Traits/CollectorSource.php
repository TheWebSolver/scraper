<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits;

use ReflectionClass;
use TheWebSolver\Codegarage\Scraper\Attributes\CollectUsing;

trait CollectorSource {
	private CollectUsing $collectableSource;

	public function getCollectorSource(): ?CollectUsing {
		return $this->collectableSource ?? null;
	}

	public function setCollectorSource( CollectUsing $source ): void {
		$this->collectableSource = $source;
	}

	protected function collectableFromAttribute(): static {
		$attribute = ( new ReflectionClass( $this ) )->getAttributes( CollectUsing::class )[0] ?? null;

		$attribute && $this->setCollectorSource( $attribute->newInstance() );

		return $this;
	}
}
