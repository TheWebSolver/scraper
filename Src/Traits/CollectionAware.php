<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits;

use BackedEnum;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Interfaces\Collectable;

trait CollectionAware {
	/** @var string[] */
	private array $collectableItems;
	/** @var string[] */
	private array $collectionKeys = array();
	private ?string $indexKey     = null;

	/** @param class-string<BackedEnum>|string[] $keys */
	public function useKeys( string|array $keys, string|BackedEnum|null $indexKey = null ): void {
		$this->collectionKeys = match ( true ) {
			is_array( $keys )                  => $keys,
			$this->isCollectableClass( $keys ) => $this->getKeysFromCollectableClass( $keys ),
			default                            => $this->throwInvalidCollectable( $keys ),
		};

		! is_null( $indexKey )
			&& ( $this->indexKey = $indexKey instanceof BackedEnum ? (string) $indexKey->value : $indexKey );
	}

	public function getKeys(): array {
		return $this->collectionKeys;
	}

	public function getIndexKey(): ?string {
		return $this->indexKey;
	}

	/**
	 * Allows exhibit to exclude non-mappable collection items.
	 *
	 * @return array<string|BackedEnum>
	 */
	protected function nonMappableItems(): array {
		return array();
	}

	/** @param ?class-string<Collectable> $classname */
	protected function setCollectionItemsFrom( ?string $classname = null ): static {
		( $classname ??= $this->collectableClass() )
			&& ( $this->collectableItems ??= $classname::toArray( ...$this->nonMappableItems() ) );

		return $this;
	}

	/** @return string[] */
	protected function getCollectableNames(): array {
		return $this->collectableItems ?? array();
	}

	/**
	 * @param array<TValue> $set The raw data to be filtered with collection keys.
	 * @return array<TValue>
	 * @template TValue
	 */
	final protected function onlyCollectable( array $set ): array {
		return array_intersect_key( $set, array_values( $this->getCollectableNames() ) );
	}

	/**
	 * @param array<string,TValue> $set The raw data to be filtered with collection keys.
	 * @return array<string,TValue>
	 * @template TValue
	 */
	final protected function withRequestedKeys( array $set ): array {
		return array_intersect_key( $set, array_flip( $this->getKeys() ) );
	}

	// phpcs:disable Squiz.Commenting.FunctionComment.InvalidNoReturn
	/**
	 * @return class-string<Collectable>
	 * @throws InvalidSource When this method is used without overriding.
	 */
	// phpcs:enable
	protected function collectableClass(): string {
		throw new InvalidSource(
			sprintf(
				'Exhibiting class must override this method "%1$s::%2$s" before use.',
				static::class,
				__FUNCTION__
			)
		);
	}

	/**
	 * @param ?class-string<Collectable> $name
	 * @return string[]
	 */
	private function getKeysFromCollectableClass( ?string $name = null ): array {
		return $this->setCollectionItemsFrom( $name )->getCollectableNames();
	}

	/** @phpstan-assert-if-true =class-string<Collectable> $value */
	private function isCollectableClass( string $value ): bool {
		return is_a( $value, Collectable::class, allow_string: true );
	}

	private function throwInvalidCollectable( string $value ): never {
		throw new InvalidSource(
			sprintf(
				'Collection keys can either be an array of string or a class string of "%1$s" interface. %2$s given.',
				Collectable::class,
				$value
			)
		);
	}
}
