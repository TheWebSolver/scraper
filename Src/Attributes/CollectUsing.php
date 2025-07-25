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
	 * @param class-string<BackedEnum<string>> $enumClass                 The BackedEnum classname whose case values will be mapped as keys of collected items.
	 * @param ?BackedEnum<string>              $indexKey                  The key whose value to be used as items' dataset key.
	 * @param BackedEnum<string>|string|null   $subsetCaseOrValueOrOffset Accepts subsets of enum cases or cases in different order than defined in the enum.
	 *                                                                    Cases passed must be in sequential order they gets mapped to items being collected.
	 *                                                                    If an in-between case/item needs to be omitted, `null` must be passed to offset it.
	 * @throws InvalidSource When `$enumClass` is not a full qualified enum classname.
	 * @no-named-arguments
	 */
	public function __construct(
		public string $enumClass,
		?BackedEnum $indexKey = null,
		BackedEnum|null|string ...$subsetCaseOrValueOrOffset
	) {
		is_a( $enumClass, BackedEnum::class, allow_string: true ) || throw InvalidSource::nonCollectableItem(
			sprintf( 'with invalid enum classname "%s" to compute', $enumClass )
		);

		[$this->all, $this->items, $this->offsets] = $this->computeFor( ...$subsetCaseOrValueOrOffset );
		$this->indexKey                            = $indexKey->value ?? null;
	}

	/**
	 * @param non-empty-list<?string> $names   Names used for collection. These must be passed in sequential order as
	 *                                         they gets mapped to items being collected. If an in-between item needs
	 *                                         to be omitted, `null` must be passed to offset it. Be aware that `null`
	 *                                         is forbidden when using in combination with `$compute` set as `false`.
	 * @param bool                    $compute When this is `false`, `$names` are set as _all_ property value and
	 *                                         _items_ property value without computing any in-between offsets.
	 * @throws InvalidSource When given `$indexKey` not found in `$names` or when `null` passed with `$compute` as `false`.
	 */
	public static function arrayOf( array $names, ?string $indexKey = null, bool $compute = true ): self {
		$indexKey && ! in_array( $indexKey, $names, true ) && throw new InvalidSource(
			sprintf( 'Index key "%1$s" not found within names: ["%2$s"].', $indexKey, self::stringifyNames( $names ) )
		);

		$_this = ( $reflection = new ReflectionClass( self::class ) )->newInstanceWithoutConstructor();

		if ( $compute ) {
			$computed = self::doComputationFor( $_this, ...$names );
		} else {
			in_array( null, $names, true ) && throw new InvalidSource(
				sprintf(
					'When computation is disabled, "null" (offset) not allowed within names: ["%s"].',
					self::stringifyNames( $names )
				)
			);
		}

		[$all, $items, $offsets] = $computed ?? [ $names, $names, [] ];

		$reflection->getProperty( 'indexKey' )->setValue( $_this, $indexKey );
		$reflection->getProperty( 'all' )->setValue( $_this, $all );
		$reflection->getProperty( 'items' )->setValue( $_this, $items );
		$reflection->getProperty( 'offsets' )->setValue( $_this, $offsets );

		return $_this;
	}

	/**
	 * Gets new instance after re-computing offset between subset of items already registered as collectables.
	 *
	 * @param BackedEnum<string>|string ...$caseOrValue
	 * @see CollectUsing::recomputationOf()
	 */
	public function subsetOf( BackedEnum|string ...$caseOrValue ): self {
		if ( ! $caseOrValue ) {
			return $this;
		}

		$props                               = get_object_vars( $this );
		[$props['items'], $props['offsets']] = $this->recomputationOf( ...$caseOrValue );

		$_this = ( $reflection = new ReflectionClass( self::class ) )->newInstanceWithoutConstructor();

		foreach ( $props as $name => $value ) {
			// Uninitialized only if instantiated with CollectUsing::arrayOf() method.
			$ignoreEnumProperty = 'enumClass' === $name && null === $value;

			$ignoreEnumProperty || $reflection->getProperty( $name )->setValue( $_this, $value );
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
	 * Consequently, the order in which `$subsetCaseOrValue` passed does not matter.
	 *
	 * @param BackedEnum<string>|string ...$subsetCaseOrValue
	 * @return array{0:array<int,string>,1:(string|int)[]} Recomputed items and offset positions.
	 * @throws InvalidSource When none of given subset items were registered during instantiation.
	 */
	public function recomputationOf( BackedEnum|string ...$subsetCaseOrValue ): array {
		if ( ! $subsetCaseOrValue ) {
			return [ $this->items, $this->offsets ];
		}

		$values = array_map( $this->toString( ... ), $subsetCaseOrValue );
		( $items = array_intersect( $this->items, $values ) ) || $this->throwRecomputationMismatch( $values );
		$lastKey = array_key_last( $items );
		$offsets = $lastKey ? array_keys( array_diff_key( range( 0, $lastKey ), $items ) ) : [];

		return [ $items, $offsets ];
	}

	/** @return class-string<BackedEnum<string>> */
	private function enum(): ?string {
		return $this->enumClass ?? null;
	}

	/**
	 * @param BackedEnum<string>|string $caseOrValue
	 * @throws InvalidSource When given item is string and cannot instantiate any enum case.
	 */
	private function toString( BackedEnum|string $caseOrValue ): string {
		if ( $caseOrValue instanceof BackedEnum ) {
			return $caseOrValue->value;
		}

		return ( ! $enum = $this->enum() ) || $enum::tryFrom( $caseOrValue )
			? $caseOrValue
			: throw InvalidSource::nonCollectableItem(
				reason: sprintf( 'for enum "%s". Cannot translate to corresponding case from given', $enum ),
				names: [ $caseOrValue ]
			);
	}

	/**
	 * @param BackedEnum<string>|string|null ...$caseOrValueOrOffset
	 * @return array{0:list<?string>,1:non-empty-array<int,string>,2:list<int>}
	 * @throws InvalidSource When enum has no case defined or all given subset cases are `null`.
	 * @no-named-arguments
	 */
	private function computeFor( BackedEnum|string|null ...$caseOrValueOrOffset ): array {
		$enum = $this->enumClass;

		if ( ! $caseOrValueOrOffset ) {
			$allItems = array_column( $enum::cases(), 'value' );

			return $allItems ? [ $allItems, $allItems, [] ] : throw InvalidSource::nonCollectableItem(
				sprintf( 'during computation with enum "%s". It does not have any', $enum )
			);
		}

		return self::doComputationFor( $this, ...$caseOrValueOrOffset );
	}

	/**
	 * @param BackedEnum<string>|string|null ...$names
	 * @return array{0:list<?string>,1:non-empty-array<int,string>,2:list<int>}
	 * @no-named-arguments
	 */
	private static function doComputationFor( self $self, BackedEnum|string|null ...$names ): array {
		$items         = $offsets = $all = [];
		$lastNameFound = false;

		for ( $i = array_key_last( $names ); $i >= 0; $i-- ) {
			$name = $names[ $i ];

			if ( null === $name ) {
				$all[]                       = null;
				$lastNameFound && $offsets[] = (int) $i;
			} else {
				$lastNameFound     = true;
				$items[ (int) $i ] = $all[] = $self->toString( $name );
			}
		}

		$items ?: self::throwComputedAllNull( $self->enum() );

		return [ array_reverse( $all ), array_reverse( $items, preserve_keys: true ), array_reverse( $offsets ) ];
	}

	/** @param array<?string> $names */
	private static function stringifyNames( array $names, bool $mapNull = true ): string {
		$mapNull && $names = array_map( static fn( ?string $v ): string => $v ??= '{{NULL}}', $names );

		return implode( '", "', $names );
	}

	/**
	 * @param string[] $values
	 * @throws InvalidSource Mismatched values.
	 */
	private function throwRecomputationMismatch( array $values ): never {
		$names  = $this->stringifyNames( $this->items, mapNull: false );
		$prefix = 'during re-computation' . ( ( $enum = $this->enum() ) ? " with enum \"{$enum}\"" : '' );

		throw InvalidSource::nonCollectableItem( "{$prefix}. Allowed values: [\"{$names}\"]. Given", $values );
	}

	private static function throwComputedAllNull( ?string $enumClass ): never {
		$prefix = 'during computation' . ( $enumClass ? " with enum \"{$enumClass}\"" : '' );

		throw InvalidSource::nonCollectableItem( "{$prefix}. All given arguments are \"null\" and none of them are valid" );
	}
}
