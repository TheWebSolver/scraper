<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits;

use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;

trait CachePath {
	protected ?string $cacheDirPath = '';
	protected string $cacheFileName = '';
	protected string $cacheRealPath;

	abstract protected function defaultCachePath(): string;

	public function withCachePath( ?string $dirpath, string $filename ): static {
		$this->cacheDirPath  = $dirpath;
		$this->cacheFileName = $filename;

		if ( $filename ) {
			$this->cacheRealPath = $this->getDirPath() . $filename;
		}

		return $this;
	}

	public function getCachePath(): string {
		return $this->cacheRealPath ?? '';
	}

	/**
	 * Ensures caching is disabled when directory path is set to null.
	 *
	 * @phpstan-assert-if-true =null $this->cacheDirPath
	 */
	protected function isCachingDisabled(): bool {
		return is_null( $this->cacheDirPath );
	}

	protected function withoutExtension( string $filename ): string {
		return substr( $filename, offset: 0, length: strrpos( $filename, '.' ) ?: null );
	}

	/**
	 * Gets the cache directory path with succeeding directory separator.
	 *
	 * @throws InvalidSource When cache directory path cannot be discovered.
	 */
	protected function getDirPath(): string {
		return $this->withTrailingSeparator( $this->getRealDirPath() ?? $this->throwInvalidDirPath() );
	}

	protected function getFileName(): string {
		return $this->cacheFileName;
	}

	private function getRealDirPath(): ?string {
		return ( $p = ( $this->cacheDirPath ?: $this->defaultCachePath() ) )
			? ( realpath( $p ) ?: null )
			: null;
	}

	private function withTrailingSeparator( string $path ): string {
		return rtrim( $path, '\\/' ) . DIRECTORY_SEPARATOR;
	}

	private function throwInvalidDirPath(): never {
		throw new InvalidSource( sprintf( 'Cache directory path not defined for class: "%s".', static::class ) );
	}
}
