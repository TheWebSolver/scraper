<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Attributes;

use Closure;
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
			sprintf(
				'Index key "%1$s" not found within names: ["%2$s"].',
				$indexKey,
				self::stringifyNames( $names )
			)
		);

		if ( $compute ) {
			$computed = self::doComputationWith( $names );
		} else {
			in_array( null, $names, true ) && throw new InvalidSource(
				sprintf(
					'When computation is disabled, "null" (offset) not allowed within names: ["%s"].',
					self::stringifyNames( $names )
				)
			);
		}

		$_this = ( $reflection = new ReflectionClass( self::class ) )->newInstanceWithoutConstructor();

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
	 * @param string|BackedEnum<string> ...$cases
	 * @see CollectUsing::recomputeFor()
	 */
	public function subsetOf( string|BackedEnum ...$cases ): self {
		if ( ! $cases ) {
			return $this;
		}

		$props                               = get_object_vars( $this );
		[$props['items'], $props['offsets']] = $this->recomputationOf( ...$cases );

		$_this = ( $reflection = new ReflectionClass( self::class ) )->newInstanceWithoutConstructor();

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
	 * @throws InvalidSource When none of given subset cases were registered during instantiation.
	 */
	public function recomputationOf( string|BackedEnum ...$subsetCases ): array {
		if ( ! $subsetCases ) {
			return [ $this->items, $this->offsets ];
		}

		$caseValues = array_map( $this->toString( ... ), $subsetCases );

		if ( ! $items = array_intersect( $this->items, $caseValues ) ) {
			$values = [ $this->enumClass, implode( '", "', $this->items ) ];

			throw InvalidSource::nonCollectableItem(
				reason: sprintf( 'during re-computation with enum "%s". Allowed enum case values: ["%s"]. Given', ...$values ),
				names: $caseValues
			);
		}

		$lastKey = array_key_last( $items );
		$offsets = $lastKey ? array_keys( array_diff_key( range( 0, $lastKey ), $items ) ) : [];

		return [ $items, $offsets ];
	}

	/**
	 * @param string|BackedEnum<string> $case
	 * @throws InvalidSource When given item is string and cannot instantiate any enum case.
	 */
	private function toString( string|BackedEnum $case ): string {
		return $case instanceof BackedEnum ? $case->value : (
			$this->enumClass::tryFrom( $case ) ? $case : throw InvalidSource::nonCollectableItem(
				reason: sprintf( 'for enum "%s". Cannot translate to corresponding case from given', $this->enumClass ),
				names: [ $case ]
			)
		);
	}

	/**
	 * @param list<string|BackedEnum<string>|null> $subsetCases
	 * @return array{0:list<?string>,1:non-empty-array<int,string>,2:list<int>}
	 * @throws InvalidSource When enum has no case defined or all given subset cases are `null`.
	 */
	private function computeFor( array $subsetCases ): array {
		$enum = $this->enumClass;

		if ( ! $subsetCases ) {
			$allItems = array_column( $enum::cases(), 'value' );

			return $allItems ? [ $allItems, $allItems, [] ] : throw InvalidSource::nonCollectableItem(
				sprintf( 'during computation with enum "%s". It does not have any', $enum )
			);
		}

		$computed = self::doComputationWith( $subsetCases, normalize: $this->toString( ... ) );

		$computed[1] ?: throw InvalidSource::nonCollectableItem(
			sprintf( 'during computation with enum "%s". All given subsets are "null" and none of them are', $enum )
		);

		return $computed;
	}

	/**
	 * @param non-empty-list<string|BackedEnum<string>|null> $subsetCases
	 * @param Closure(string|BackedEnum<string>):string      $normalize
	 * @return array{0:list<?string>,1:array<int,string>,2:list<int>}
	 */
	private static function doComputationWith( array $subsetCases, ?Closure $normalize = null ): array {
		$items         = $offsets = $all = [];
		$lastCaseFound = false;

		for ( $i = array_key_last( $subsetCases ); $i >= 0; $i-- ) {
			$case = $subsetCases[ $i ];

			if ( null === $case ) {
				$all[]                       = null;
				$lastCaseFound && $offsets[] = $i;
			} else {
				$lastCaseFound = true;
				$case          = $normalize ? $normalize( $case ) : ( $case instanceof BackedEnum ? $case->value : $case );
				$items[ $i ]   = $all[] = $case;
			}
		}

		$items = $items ? array_reverse( $items, preserve_keys: true ) : $items;

		return [ array_reverse( $all ), $items, array_reverse( $offsets ) ];
	}

	/** @param array<?string> $names */
	private static function stringifyNames( array $names ): string {
		return implode( '", "', array_map( static fn( ?string $v ) => $v ??= '{{NULL}}', $names ) );
	}
}
