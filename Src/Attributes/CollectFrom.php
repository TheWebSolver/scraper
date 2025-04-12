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
	 * @param class-string<BackedEnum> $enumClass The BackedEnum classname whose cases will be used as mappable keys.
	 * @param string|BackedEnum        ...$only   If only subset of mappable keys is required and passed as arg to
	 *                                            this param, only these keys will be used. Passed order matters.
	 */
	public function __construct( public string $enumClass, string|BackedEnum ...$only ) {
		$this->items = array_map( self::collect( ... ), $only ?: $enumClass::cases() );
	}

	private static function collect( string|BackedEnum $item ): string {
		return $item instanceof BackedEnum ? (string) $item->value : $item;
	}
}
