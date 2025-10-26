<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Integration\Cli;

use Closure;
use ArrayObject;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\TableFactory;
use TheWebSolver\Codegarage\Scraper\Enums\FileFormat;
use TheWebSolver\Codegarage\Scraper\Traits\CachePath;
use TheWebSolver\Codegarage\Scraper\Event\TableTraced;
use TheWebSolver\Codegarage\Scraper\Enums\AccentedChars;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Attributes\CollectUsing;
use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedCharacter;

/** @template ColumnDataType */
trait TableConsole {
	use CachePath;

	/** @placeholder: `%s:` source location. */
	public const SCRAPE_FINISHED = 'Finished scraping content from source: %s';
	/** @placeholder `%s:` Cli input to invalidate cache. */
	public const SCRAPE_CACHED = 'Cached scraped content forever unless %s is used';

	public const BEFORE_SCRAPE_INVALIDATION = [
		'pass' => 'Invalidating previously scraped content',
		'fail' => 'Could not find previously scraped content',
	];

	public const AFTER_SCRAPE_INVALIDATION = [
		'pass' => 'Scraped content invalidated successfully',
		'fail' => 'Could not invalidate scraped content',
	];

	/** @placeholder `1:` source type, `2:` source location. */
	public const FETCHING_CONTENT = 'Fetching content from %1$s: <href="%2$s">%2$s</>';
	/** @placeholder `%s:` context. */
	public const PARSING_STARTED = 'Parsing %s from fetched content';
	/** @placeholder `%s:` context. */
	public const PARSING_FINISHED = 'Finished parsing %s from fetched content';

	private ?CollectUsing $tableRowsCollectionSource = null;

	/** @return TableFactory<ColumnDataType,TableTracer<ColumnDataType>> */
	abstract protected function tableFactory(): TableFactory;
	abstract protected function getTableContextForOutput(): string;

	/**
	 * @return array{
	 *  indexKey   : ?string,
	 *  datasetKeys: ?non-empty-list<string>,
	 *  accent     : ?non-empty-string,
	 *  filename   : string,
	 *  extension  : string
	 * }
	 */
	abstract protected function getInputValue(): array;

	/** @return array{indexKey:?string,datasetKeys:?non-empty-list<string>,accent:?non-empty-string} */
	protected function getInputDefaultsForOutput(): array {
		return [
			'indexKey'    => $this->tableRowsCollectionSource?->indexKey,
			'datasetKeys' => empty( $items = $this->tableRowsCollectionSource?->items ) ? null : array_values( $items ),
			'accent'      => $this->getAccentableTracer()?->getAccentOperationType()?->action(),
		];
	}

	protected function invalidateScraperCache( ?Closure $writeOutput ): void {
		$cacheStatusMsg = $this->tableFactory()->scraper()->hasCache()
			? self::BEFORE_SCRAPE_INVALIDATION['pass']
			: self::BEFORE_SCRAPE_INVALIDATION['fail'];

		$writeOutput && $writeOutput( $cacheStatusMsg );

		$cacheClearedMsg = $this->tableFactory()->scraper()->invalidateCache()
			? self::AFTER_SCRAPE_INVALIDATION['pass']
			: self::AFTER_SCRAPE_INVALIDATION['fail'];

		$writeOutput && $writeOutput( $cacheClearedMsg );
	}

	protected function setIndicesSourceFromInput( TableTraced $event ): void {
		if ( ! $itemsFromInput = $this->getInputValue()['datasetKeys'] ) {
			return;
		}

		$collection = $event->tracer->getIndicesSource()?->subsetOf( ...$itemsFromInput )
			?? CollectUsing::listOf( $itemsFromInput );

		try {
			( $key = $this->getInputValue()['indexKey'] ) && ( $collection = $collection->indexKeyAs( $key ) );
		} catch ( InvalidSource ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// We won't throw exception caused by index & collection keys from the user input.
			// This behavior and console output must be handled by the CLI package itself.
		} finally {
			$event->tracer->setIndicesSource( $this->tableRowsCollectionSource = $collection );
		}
	}

	/** @return array<ArrayObject<array-key,ColumnDataType>> */
	protected function scrapeAndParseTableRows( bool $ignoreCache, ?Closure $outputWriter ): array {
		$ignoreCache && $this->invalidateScraperCache( $outputWriter );

		if ( $outputWriter ) {
			$outputWriter( PHP_EOL );
			$outputWriter( sprintf( self::FETCHING_CONTENT, ...$this->getScrapeSource() ) );
		}

		$scraper = $this->tableFactory()->scraper();

		$this->setAccentOperationTypeFromInput();

		$scraper->getTableTracer()->addEventListener( Table::Row, $this->setIndicesSourceFromInput( ... ) );

		$actions = $this->getScraperActions( $outputWriter );
		$rows    = iterator_to_array( $this->tableFactory()->generateRowIterator( $actions, $ignoreCache ) );

		$outputWriter && $outputWriter( sprintf( self::PARSING_FINISHED, $this->getTableContextForOutput() ) );

		$scraper->flush();

		return $rows;
	}

	/**
	 * @return array{
	 *  data : array<ArrayObject<array-key,ColumnDataType>>,
	 *  cache: ?array{path:string,bytes:int|false,content:non-empty-string|false}
	 * }
	 */
	protected function getTableRows( bool $ignoreCache, ?Closure $outputWriter ): array {
		$data  = $this->scrapeAndParseTableRows( $ignoreCache, $outputWriter );
		$cache = null;

		if ( empty( $data ) || $this->isCachingDisabled() ) {
			return compact( 'data', 'cache' );
		}

		$format = FileFormat::tryFrom( $this->getInputValue()['extension'] ) ?? FileFormat::Json;
		$name   = $this->withoutExtension( $this->getInputValue()['filename'] ?: $this->getFileName() );

		$this->withCachePath( $this->getDirPath(), filename: "{$name}.{$format->value}" );

		$writer           = $format->getWriter( $data );
		$cache['path']    = $this->getCachePath();
		$cache['bytes']   = $writer->write( $this->getCachePath(), $this->getInputValue() );
		$cache['content'] = $writer->getContent();

		return compact( 'data', 'cache' );
	}

	/** @return array{0:string,1:string} */
	private function getScrapeSource(): array {
		return ( $scraper = $this->tableFactory()->scraper() )->hasCache()
			? [ 'Cache', $scraper->getCachePath() ]
			: [ 'URL', $scraper->getSourceUrl() ];
	}

	/** @return ($verboseOutput is null ? null : array{'afterScrape':Closure(string):void,'afterCache':Closure}) */
	private function getScraperActions( ?Closure $verboseOutput ): ?array {
		if ( ! $verboseOutput ) {
			return null;
		}

		return [
			'afterScrape' => static function ( string $url ) use ( $verboseOutput ) {
				$verboseOutput( sprintf( self::SCRAPE_FINISHED, $url ) );
			},
			'afterCache'  => function () use ( $verboseOutput ) {
				$verboseOutput( sprintf( self::SCRAPE_CACHED, '"--force" flag' ) );
				$verboseOutput( sprintf( self::PARSING_STARTED, $this->getTableContextForOutput() ) );
			},
		];
	}

	private function getAccentableTracer(): ?AccentedCharacter {
		return ( $t = $this->tableFactory()->scraper()->getTableTracer() ) instanceof AccentedCharacter ? $t : null;
	}

	private function setAccentOperationTypeFromInput(): bool {
		if ( ! $tracer = $this->getAccentableTracer() ) {
			return false;
		}

		if ( ! $action = AccentedChars::tryFromName( $this->getInputValue()['accent'] ?? '' ) ) {
			return false;
		}

		$tracer->setAccentOperationType( $action );

		return true;
	}
}
