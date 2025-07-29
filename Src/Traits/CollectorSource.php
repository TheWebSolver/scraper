<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits;

use ReflectionClass;
use TheWebSolver\Codegarage\Scraper\Attributes\CollectUsing;

trait CollectorSource {
	protected function collectableFromAttribute(): ?CollectUsing {
		$attribute = ( new ReflectionClass( $this ) )->getAttributes( CollectUsing::class )[0] ?? null;

		return $attribute ? $attribute->newInstance() : null;
	}
}
