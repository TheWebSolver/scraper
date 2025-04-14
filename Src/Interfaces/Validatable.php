<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use TheWebSolver\Codegarage\Scraper\Error\ValidationFail;

/** @template TValue */
interface Validatable {
	/**
	 * Validates given data.
	 *
	 * @param TValue $data
	 * @throws ValidationFail When given data is not valid after validation is applied.
	 */
	public function validate( mixed $data ): void;
}
