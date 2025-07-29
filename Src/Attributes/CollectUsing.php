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
	 * @throws InvalidSource When `$enumClass` is not a enum classname, enum has no case defined, or all `subsetCaseOrValueOrOffset` are null.
	 * @no-named-arguments
	 */
	public function __construct(
		private string $enumClass,
		?BackedEnum $indexKey = null,
		BackedEnum|null|string ...$subsetCaseOrValueOrOffset
	) {
		is_a( $enumClass, BackedEnum::class, allow_string: true ) || throw InvalidSource::nonCollectableItem(
			reason: "with invalid enum classname \"{$enumClass}\" to compute"
		);

		[$this->all, $this->items, $this->offsets] = $this->computeFor( ...$subsetCaseOrValueOrOffset );
		$this->indexKey                            = $indexKey->value ?? null;
	}

	/**
	 * Gets collection instance when only arbitrary collectable names array is known without any enum class.
	 *
	 * @param non-empty-list<?string> $names   Names used for collection. These must be passed in sequential order as
	 *                                         they gets mapped to items being collected. If an in-between item needs
	 *                                         to be omitted, `null` must be passed to offset it. Be aware that `null`
	 *                                         is forbidden when using in combination with `$compute` set as `false`.
	 * @param bool                    $compute When this is `false`, `$names` are set as _all_ property value and
	 *                                         _items_ property value without computing any in-between offsets.
	 * @throws InvalidSource When `$names` empty, `null` passed with `$compute` as `false`, or `$indexKey` not found in `$names`.
	 */
	public static function listOf( array $names, ?string $indexKey = null, bool $compute = false ): self {
		! ! $names || throw InvalidSource::nonCollectableItem( 'because given list is empty. Provide at-least one' );

		$indexKey && ! in_array( $indexKey, $names, true ) && throw InvalidSource::nonCollectableItem(
			reason: "because index-key must be one of the value in the given list. \"{$indexKey}\" does not exist in list of",
			names: self::mapNullToString( ...$names )
		);

		! $compute && in_array( null, $names, true ) && throw InvalidSource::nonCollectableItem(
			reason: 'because when computation is disabled, "null" (offset) must not be passed as',
			names: self::mapNullToString( ...$names )
		);

		$_this    = ( $reflection = new ReflectionClass( __CLASS__ ) )->newInstanceWithoutConstructor();
		$computed = $compute ? $_this->findOffsetsIn( ...$names ) : [ $names, $names, [] ];
		$props    = get_object_vars( $_this );

		[$props['all'], $props['items'], $props['offsets']] = $computed;

		// phpcs:ignore -- We know what we are doing with array. Its OK!
		return $_this->withProperties( [ ...$props, ...compact( 'indexKey' ) ], $reflection );
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

		$reflection                          = new ReflectionClass( __CLASS__ );
		$props                               = get_object_vars( $this );
		[$props['items'], $props['offsets']] = $this->recomputationOf( ...$caseOrValue );

		return $reflection->newInstanceWithoutConstructor()->withProperties( $props, $reflection );
	}

	/**
	 * Re-computes offset between subset of items already registered as collectables.
	 *
	 * Note that recomputation is based on previously set order of collectable items.
	 * The order cannot be changed here. It only computes offsets between items in
	 * same sequence they were registered at the time the class was instantiated.
	 *
	 * Consequently, the order in which `$subsetCaseOrValue` is passed does not matter.
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
		( $items = array_intersect( $this->items, $values ) ) || $this->throwRecomputationMismatch( ...$values );
		$lastKey = array_key_last( $items );
		$offsets = $lastKey ? array_keys( array_diff_key( range( 0, $lastKey ), $items ) ) : [];

		return [ $items, $offsets ];
	}

	/** @return class-string<BackedEnum<string>> */
	private function enum(): ?string {
		return $this->enumClass ?? null;
	}

	/**
	 * @param array<string,mixed>     $props
	 * @param ReflectionClass<object> $reflection
	 */
	private function withProperties( array $props, ReflectionClass $reflection ): self {
		if ( ! $this->enum() ) {
			unset( $props['enumClass'] ); // Not required for statically instantiated collection.
		}

		foreach ( $props as $name => $value ) {
			$reflection->getProperty( $name )->setValue( $this, $value );
		}

		return $this;
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
				reason: "for enum \"$enum\". Cannot translate to corresponding case from given",
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
		if ( $caseOrValueOrOffset ) {
			return $this->findOffsetsIn( ...$caseOrValueOrOffset );
		}

		$caseValues = array_column( $this->enumClass::cases(), 'value' );

		return $caseValues ? [ $caseValues, $caseValues, [] ] : throw InvalidSource::nonCollectableItem(
			reason: "because given enum \"{$this->enumClass}\" does not have any case to use as"
		);
	}

	/**
	 * @param BackedEnum<string>|string|null ...$caseOrValueOrOffset
	 * @return array{0:list<?string>,1:non-empty-array<int,string>,2:list<int>}
	 * @throws InvalidSource When all args are null.
	 * @no-named-arguments
	 */
	private function findOffsetsIn( BackedEnum|string|null ...$caseOrValueOrOffset ): array {
		$items          = $offsets = $all = [];
		$lastValueFound = false;

		for ( $i = array_key_last( $caseOrValueOrOffset ); $i >= 0; $i-- ) {
			$value = $caseOrValueOrOffset[ $i ];

			if ( null === $value ) {
				$all[]                        = null;
				$lastValueFound && $offsets[] = (int) $i;
			} else {
				$lastValueFound    = true;
				$items[ (int) $i ] = $all[] = $this->toString( $value );
			}
		}

		$items ?: throw InvalidSource::nonCollectableItem(
			reason: "{$this->pre()}. All given arguments are \"null\" and none of them are valid"
		);

		return [ array_reverse( $all ), array_reverse( $items, preserve_keys: true ), array_reverse( $offsets ) ];
	}

	/** @return array<string> */
	private static function mapNullToString( ?string ...$values ): array {
		return array_map( static fn( ?string $v ): string => $v ??= '{{NULL}}', $values );
	}

	private function throwRecomputationMismatch( string ...$values ): never {
		$items  = implode( '", "', $this->items );
		$plural = 1 === count( $this->items ) ? '' : 's';

		throw InvalidSource::nonCollectableItem( "{$this->pre( 're-' )}. Allowed value{$plural}: [\"{$items}\"]. Given", $values );
	}

	private function pre( string $re = '' ): string {
		return "during {$re}computation" . ( ( $enum = $this->enum() ) ? " with enum \"{$enum}\"" : '' );
	}
}
