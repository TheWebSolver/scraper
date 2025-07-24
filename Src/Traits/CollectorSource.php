<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits;

use ReflectionClass;
use TheWebSolver\Codegarage\Scraper\Attributes\CollectUsing;

trait CollectorSource {
	private CollectUsing $collectionSource;

	public function getCollectorSource(): ?CollectUsing {
		return $this->collectionSource ?? null;
	}

	public function setCollectorSource( CollectUsing $source ): void {
		$this->collectionSource = $source;
	}

	/** @param ReflectionClass<static> $reflection */
	protected function collectableFromAttribute( ReflectionClass $reflection ): static {
		( $attribute = ( $reflection->getAttributes( CollectUsing::class )[0] ?? null ) )
				&& $this->collectionSource = $attribute->newInstance();

		return $this;
	}
}
