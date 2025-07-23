<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Attributes;

use Attribute;
use BackedEnum;
use ReflectionClass;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;

#[Attribute( flags: Attribute::TARGET_CLASS )]
final readonly class CollectUsing {
	/** @var list<?string> */
	public array $all;
	/** @var non-empty-array<int,string> */
	public array $items;
	/** @var list<int> */
	public array $offsets;
	public ?string $indexKey;

	/**
	 * @param class-string<BackedEnum<string>> $enumClass   The BackedEnum classname whose case values will be used as mappable keys.
	 * @param ?BackedEnum<string>              $indexKey    The key whose value to be used as dataset key.
	 * @param null|string|BackedEnum<string>   $subsetItems Accepts subset of mappable keys or keys in different order than defined as enum cases.
	 *                                                      Value passed must be in sequential order they gets mapped to items being collected.
	 *                                                      If an in-between item needs to be omitted, `null` must be passed to offset it.
	 * @no-named-arguments
	 */
	public function __construct(
		public string $enumClass,
		?BackedEnum $indexKey = null,
		null|string|BackedEnum ...$subsetItems
	) {
		[$this->all, $this->items, $this->offsets] = $this->computeFor( $subsetItems );
		$this->indexKey                            = $indexKey->value ?? null;
	}

	/**
	 * Gets new instance after re-computing offset between subset of items already registered as collectables.
	 *
	 * @param string|BackedEnum<string> $subsetItems
	 * @see CollectUsing::recomputeFor()
	 */
	public function with( string|BackedEnum ...$subsetItems ): self {
		if ( ! $subsetItems ) {
			return $this;
		}

		[$subsets, $offsets] = $this->recomputeFor( ...$subsetItems );

		$self = ( $reflection = new ReflectionClass( self::class ) )->newInstanceWithoutConstructor();

		$reflection->getProperty( 'enumClass' )->setValue( $self, $this->enumClass );
		$reflection->getProperty( 'all' )->setValue( $self, $this->all );
		$reflection->getProperty( 'items' )->setValue( $self, $subsets );
		$reflection->getProperty( 'offsets' )->setValue( $self, $offsets );
		$reflection->getProperty( 'indexKey' )->setValue( $self, $this->indexKey );

		return $self;
	}

	/**
	 * Re-computes offset between subset of items already registered as collectables.
	 *
	 * Note that recomputation is based on previously set order of collectable items.
	 * The order cannot be changed here. It only computes offsets between items in
	 * same sequence they were registered at the time the class was instantiated.
	 *
	 * Consequently, the order in which `$subsetItems` are passed does not matter.
	 *
	 * @param string|BackedEnum<string> $subsetItems
	 * @return array{0:array<int,string>,1:(string|int)[]} Recomputed items and offset positions.
	 */
	public function recomputeFor( string|BackedEnum ...$subsetItems ): array {
		if ( ! $subsetItems ) {
			return [ $this->items, [] ];
		}

		$subsets       = $offsets = [];
		$subsetItems   = array_map( $this->toString( ... ), $subsetItems );
		$lastSubsetKey = null;

		foreach ( $this->all as $key => $item ) {
			if ( in_array( $item, $subsetItems, true ) ) {
				$subsets[ $key ] = $item;
				$lastSubsetKey   = $key;
			} else {
				$offsets[] = $key;
			}
		}

		$offsets = array_filter( $offsets, static fn( int $offsetKey ) => $offsetKey < $lastSubsetKey );

		return [ $subsets, $offsets ];
	}

	/**
	 * @param string|BackedEnum<string> $item
	 * @throws InvalidSource When given item is string and cannot instantiate any enum case.
	 */
	private function toString( string|BackedEnum $item ): string {
		return $item instanceof BackedEnum ? $item->value : (
			$this->enumClass::tryFrom( $item ) ? $item : throw InvalidSource::nonCollectableItem( $item )
		);
	}

	/**
	 * @param list<string|BackedEnum<string>|null> $subsetItems
	 * @return array{0:list<?string>,1:non-empty-array<int,string>,2:list<int>}
	 */
	private function computeFor( array $subsetItems ): array {
		if ( ! $subsetItems ) {
			$all = array_column( $this->enumClass::cases(), 'value' );

			return [ $all, $all, [] ];
		}

		$subsets             = $offsets = $all = [];
		$lastSubsetItemFound = false;

		for ( $i = array_key_last( $subsetItems ); $i >= 0; $i-- ) {
			if ( null === $subsetItems[ $i ] ) {
				$all[]                             = null;
				$lastSubsetItemFound && $offsets[] = $i;
			} else {
				$subsets[ $i ]       = $all[] = $this->toString( $subsetItems[ $i ] );
				$lastSubsetItemFound = true;
			}
		}

		return [ array_reverse( $all ), array_reverse( $subsets, preserve_keys: true ), array_reverse( $offsets ) ];
	}
}
