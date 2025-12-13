<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use TheWebSolver\Codegarage\Scraper\Error\ValidationFail;

interface Validatable {
	/**
	 * Validates given data.
	 *
	 * @throws ValidationFail When given $data could not be validated.
	 */
	public function validate( mixed $data ): void;
}
