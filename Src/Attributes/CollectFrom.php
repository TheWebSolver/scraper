<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Attributes;

use Attribute;
use BackedEnum;
use TheWebSolver\Codegarage\Scraper\Interfaces\Collectable;

#[Attribute( flags: Attribute::TARGET_CLASS )]
final readonly class CollectFrom {
	/** @var list<string> */
	public array $items;

	/**
	 * @param class-string<Collectable> $concrete The concrete classname that provides collectable items as an array.
	 * @param string|BackedEnum         ...$only  If only subset of collectable items is required and passed as arg
	 *                                            to this parameter, only these items will be used. Order matters.
	 */
	public function __construct( public string $concrete, string|BackedEnum ...$only ) {
		$this->items = $this->onlyItems( $only ) ?? $concrete::toArray();
	}

	/**
	 * @param (string|BackedEnum)[] $collectables
	 * @return ?list<string>
	 */
	private function onlyItems( array $collectables ): ?array {
		if ( empty( $collectables ) ) {
			return null;
		}

		$items = array();

		foreach ( $collectables as $collectable ) {
			$items[] = $collectable instanceof BackedEnum ? (string) $collectable->value : $collectable;
		}

		return $items;
	}
}
