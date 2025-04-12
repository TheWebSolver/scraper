<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits;

use BackedEnum;
use ReflectionClass;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Attributes\CollectFrom;

trait CollectorSource {
	/** @placeholder `1:` the given source's string value. */
	private const INVALID_COLLECTION_SOURCE = 'Collection keys can either be an array of strings or a BackedEnum classname. "%s" given.';

	private CollectFrom $collectionSource;
	/** @var list<string> */
	private array $requestedKeys = array();
	private ?string $indexKey    = null;

	/** @param class-string<BackedEnum>|list<string> $keys */
	public function useKeys( string|array $keys, string|BackedEnum|null $indexKey = null ): static {
		$this->requestedKeys = match ( true ) {
			is_array( $keys )                    => $keys,
			$this->isBackedEnumMappable( $keys ) => $this->collectFromMappable( $keys )->items,
			default                              => $this->throwInvalidCollectable( $keys ),
		};

		! is_null( $indexKey )
			&& ( $this->indexKey = $indexKey instanceof BackedEnum ? (string) $indexKey->value : $indexKey );

		return $this;
	}

	public function getKeys(): array {
		return $this->requestedKeys;
	}

	public function getIndexKey(): ?string {
		return $this->indexKey;
	}

	protected function getCollectionSource(): ?CollectFrom {
		return $this->collectionSource ?? null;
	}

	/** @param ReflectionClass<static> $reflection */
	protected function collectableFromAttribute( ReflectionClass $reflection ): static {
		( $attribute = ( $reflection->getAttributes( CollectFrom::class )[0] ?? null ) )
				&& $this->collectionSource = $attribute->newInstance();

		return $this;
	}

	/** @param class-string<BackedEnum> $enumClass */
	protected function collectFromMappable( string $enumClass, string|BackedEnum ...$only ): CollectFrom {
		return $this->collectionSource = new CollectFrom( $enumClass, ...$only );
	}

	final protected function isRequestedKey( string $collectable ): bool {
		return in_array( $collectable, $this->getKeys(), strict: true );
	}

	/** @phpstan-assert-if-true =class-string<BackedEnum> $value */
	private function isBackedEnumMappable( string $value ): bool {
		return is_a( $value, BackedEnum::class, allow_string: true );
	}

	private function throwInvalidCollectable( string $value ): never {
		throw new InvalidSource( sprintf( self::INVALID_COLLECTION_SOURCE, $value ) );
	}
}
