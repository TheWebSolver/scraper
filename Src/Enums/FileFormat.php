<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Enums;

use InvalidArgumentException;
use TheWebSolver\Codegarage\Scraper\Helper\JsonWriter;
use TheWebSolver\Codegarage\Scraper\Interfaces\Writable;

enum FileFormat: string {
	case Json = 'json';
	case Csv  = 'csv';

	/**
	 * @param iterable<array-key,TValue> $content
	 * @throws InvalidArgumentException When unsupported format used.
	 * @template TValue
	 */
	public function getWriter( iterable $content ): Writable {
		return match ( $this ) {
			default    => throw new InvalidArgumentException( "Unsupported format: {$this->value}" ),
			self::Json => new JsonWriter( $content ),
		};
	}
}
