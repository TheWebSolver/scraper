<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Data;

/** @template TValue */
final readonly class TableCell {
	/** @param TValue $value */
	public function __construct( public mixed $value, public int $rowSpan, public int $position ) {}

	public function hasValue(): bool {
		return null !== $this->value && '' !== $this->value;
	}

	public function isExtendable(): bool {
		return $this->rowSpan > 1;
	}

	/** @return self<TValue> */
	public function extended( int $count = 1 ): self {
		return new self( $this->value, $this->rowSpan - $count, $this->position );
	}
}
