<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Data;

final readonly class TableHead {
	public function __construct( public bool $isValid, public bool $isAllowed, public ?string $value ) {}
}
