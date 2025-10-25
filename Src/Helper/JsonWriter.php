<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Helper;

use JsonException;
use TheWebSolver\Codegarage\Scraper\Error\WriteFail;
use TheWebSolver\Codegarage\Scraper\Interfaces\Writable;

class JsonWriter implements Writable {
	private int $flags = 0;
	/** @var non-empty-string|false */
	private string|false $json = false;

	/**
	 * @param iterable<array-key,mixed> $content
	 * @no-named-arguments
	 */
	public function __construct( private iterable $content, int ...$flags ) {
		$this->flags = $this->bitwiseFlags( ...$flags );
	}

	public function write( string $resourcePath, array $options = [] ): int|false {
		try {
			$this->json = $content = json_encode( $this->content, $this->getEncodeFlags( $options ) ) ?: false;

			return $content ? file_put_contents( $resourcePath, $content ) : false;
		} catch ( JsonException $e ) {
			throw new WriteFail( "Could not write content to a file. Reason: {$e->getMessage()}", $e->getCode(), $e );
		}
	}

	public function getContent(): string|false {
		return $this->json;
	}

	/** @param array<string,mixed> $options */
	protected function getEncodeFlags( array $options ): int {
		$shouldEscapeUnicode = 'escape' === ( $options['accent'] ?? null );
		$escapeFlags         = $shouldEscapeUnicode ? [] : [ JSON_UNESCAPED_UNICODE, JSON_UNESCAPED_SLASHES ];

		return $this->bitwiseFlags( JSON_THROW_ON_ERROR, JSON_PRETTY_PRINT, ...$escapeFlags );
	}

	private function bitwiseFlags( int ...$flags ): int {
		$bitwise = $this->flags;

		foreach ( $flags as $flag ) {
			$bitwise |= $flag;
		}

		return $bitwise;
	}
}
