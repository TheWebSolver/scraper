<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Attributes;

use Attribute;
use BackedEnum;

#[Attribute( flags: Attribute::TARGET_CLASS )]
final readonly class CollectFrom {
	/** @var list<string> */
	public array $items;

	/**
	 * @param class-string<BackedEnum<string>> $enumClass The BackedEnum classname whose case values will be used as mappable keys.
	 * @param string|BackedEnum<string>        ...$only   If only subset of mappable keys is required and passed as arg to
	 *                                                    this param, only these keys will be used. Passed order matters.
	 * @no-named-arguments
	 */
	public function __construct( public string $enumClass, string|BackedEnum ...$only ) {
		$this->items = array_map( self::collect( ... ), $only ?: $enumClass::cases() );
	}

	/** @param BackedEnum<string> $item */
	private static function collect( string|BackedEnum $item ): string {
		return $item instanceof BackedEnum ? $item->value : $item;
	}
}
