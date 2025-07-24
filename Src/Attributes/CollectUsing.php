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
	 * @param class-string<BackedEnum<string>> $enumClass   The BackedEnum classname whose case values will be mapped as keys of collected items.
	 * @param ?BackedEnum<string>              $indexKey    The key whose value to be used as items' dataset key.
	 * @param null|string|BackedEnum<string>   $subsetCases Accepts subsets of enum cases or cases in different order than defined in the enum.
	 *                                                      Cases passed must be in sequential order they gets mapped to items being collected.
	 *                                                      If an in-between case/item needs to be omitted, `null` must be passed to offset it.
	 * @no-named-arguments
	 */
	public function __construct(
		public string $enumClass,
		?BackedEnum $indexKey = null,
		null|string|BackedEnum ...$subsetCases
	) {
		[$this->all, $this->items, $this->offsets] = $this->computeFor( $subsetCases );
		$this->indexKey                            = $indexKey->value ?? null;
	}

	/**
	 * Gets new instance after re-computing offset between subset of items already registered as collectables.
	 *
	 * @param string|BackedEnum<string> ...$subsetCases
	 * @see CollectUsing::recomputeFor()
	 */
	public function with( string|BackedEnum ...$subsetCases ): self {
		if ( ! $subsetCases ) {
			return $this;
		}

		$reflection                          = new ReflectionClass( self::class );
		$_this                               = $reflection->newInstanceWithoutConstructor();
		$props                               = get_object_vars( $this );
		[$props['items'], $props['offsets']] = $this->recomputeFor( ...$subsetCases );

		foreach ( $props as $name => $value ) {
			$reflection->getProperty( $name )->setValue( $_this, $value );
		}

		return $_this;
	}

	/**
	 * Re-computes offset between subset of items already registered as collectables.
	 *
	 * Note that recomputation is based on previously set order of collectable items.
	 * The order cannot be changed here. It only computes offsets between items in
	 * same sequence they were registered at the time the class was instantiated.
	 *
	 * Consequently, the order in which `$subsetCases` are passed does not matter.
	 *
	 * @param string|BackedEnum<string> ...$subsetCases
	 * @return array{0:array<int,string>,1:(string|int)[]} Recomputed items and offset positions.
	 */
	public function recomputeFor( string|BackedEnum ...$subsetCases ): array {
		if ( ! $subsetCases ) {
			return [ $this->items, $this->offsets ];
		}

		if ( ! $items = array_intersect( $this->items, array_map( $this->toString( ... ), $subsetCases ) ) ) {
			return [ $this->items, $this->offsets ];
		}

		$lastKey = array_key_last( $items );
		$offsets = $lastKey ? array_keys( array_diff_key( range( 0, $lastKey ), $items ) ) : [];

		return [ $items, $offsets ];
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
	 * @param list<string|BackedEnum<string>|null> $subsetCases
	 * @return array{0:list<?string>,1:non-empty-array<int,string>,2:list<int>}
	 */
	private function computeFor( array $subsetCases ): array {
		if ( ! $subsetCases ) {
			$all = array_column( $this->enumClass::cases(), 'value' );

			return [ $all, $all, [] ];
		}

		$items               = $offsets = $all = [];
		$lastSubsetCaseFound = false;

		for ( $i = array_key_last( $subsetCases ); $i >= 0; $i-- ) {
			if ( null === $subsetCases[ $i ] ) {
				$all[]                             = null;
				$lastSubsetCaseFound && $offsets[] = $i;
			} else {
				$items[ $i ]         = $all[] = $this->toString( $subsetCases[ $i ] );
				$lastSubsetCaseFound = true;
			}
		}

		return [ array_reverse( $all ), array_reverse( $items, preserve_keys: true ), array_reverse( $offsets ) ];
	}
}
