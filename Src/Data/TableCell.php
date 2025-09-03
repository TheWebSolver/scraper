<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Data;

/** @template TValue */
final readonly class TableCell {
	/** @param TValue $value */
	public function __construct( public mixed $value, public int $rowspan, public int $position ) {}

	public function hasValidValue(): bool {
		return null !== $this->value && '' !== $this->value;
	}

	public function shouldExtendToNextRow(): bool {
		return $this->rowspan > 1;
	}

	public function withRemainingRowExtension( int $count = 1 ): self {
		return new self( $this->value, $this->rowspan - $count, $this->position );
	}

	public function withPositionAt( int $position ): self {
		return new self( $this->value, $this->rowspan, $position );
	}
}
