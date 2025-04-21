<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits;

use BackedEnum;
use ReflectionClass;
use TheWebSolver\Codegarage\Scraper\Attributes\CollectFrom;

trait CollectorSource {
	private CollectFrom $collectionSource;

	/** @return list<string> */
	final protected function collectSourceItems(): array {
		return $this->getCollectionSource()->items ?? [];
	}

	final protected function getCollectionSource(): ?CollectFrom {
		return $this->collectionSource ?? null;
	}

	/** @param ReflectionClass<static> $reflection */
	protected function collectableFromAttribute( ReflectionClass $reflection ): static {
		( $attribute = ( $reflection->getAttributes( CollectFrom::class )[0] ?? null ) )
				&& $this->collectionSource = $attribute->newInstance();

		return $this;
	}

	/**
	 * @param class-string<BackedEnum<string>> $enumClass
	 * @param string|BackedEnum<string>        ...$only
	 * @no-named-arguments
	 */
	protected function collectFromMappable( string $enumClass, string|BackedEnum ...$only ): CollectFrom {
		return $this->collectionSource = new CollectFrom( $enumClass, ...$only );
	}

	/** @phpstan-assert-if-true =class-string<BackedEnum<string>> $value */
	private function isBackedEnumMappable( string $value ): bool {
		return is_a( $value, BackedEnum::class, allow_string: true );
	}
}
