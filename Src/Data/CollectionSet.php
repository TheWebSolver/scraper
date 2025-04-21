<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Data;

use ArrayObject;

/** @template TReturn */
final readonly class CollectionSet {
	/** @param ArrayObject<array-key,TReturn> $value */
	public function __construct( public string|int $key, public ArrayObject $value ) {}
}
