<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use TheWebSolver\Codegarage\Scraper\Error\WriteFail;

interface Writable {
	/**
	 * Writes content by converting it to the appropriate format.
	 *
	 * @param string              $resourcePath Full filepath where content will be written.
	 * @param array<string,mixed> $options      Writer options based on writer type.
	 *
	 * @throws WriteFail When content is not writable.
	 */
	public function write( string $resourcePath, array $options = [] ): int|false;

	/**
	 * Returns the written content.
	 *
	 * @return non-empty-string|false The string representation of written content. `false` if no content.
	 */
	public function getContent(): string|false;
}
