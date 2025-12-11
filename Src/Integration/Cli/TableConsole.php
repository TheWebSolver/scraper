<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Integration\Cli;

use Closure;
use Iterator;
use ArrayObject;
use TheWebSolver\Codegarage\Scraper\Factory;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Enums\FileFormat;
use TheWebSolver\Codegarage\Scraper\Traits\CachePath;
use TheWebSolver\Codegarage\Scraper\Event\TableTraced;
use TheWebSolver\Codegarage\Scraper\Enums\AccentedChars;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Interfaces\Scrapable;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Attributes\CollectUsing;
use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedCharacter;

/** @template TableColumnValue */
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

	private ?CollectUsing $collectedUsing = null;

	/** @return Scrapable<Iterator<array-key,ArrayObject<array-key,TableColumnValue>>,TableTracer<TableColumnValue>> */
	abstract protected function scraper(): Scrapable;
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

	/** @return Factory<ArrayObject<array-key,TableColumnValue>> */
	protected function tableFactory(): Factory {
		return new Factory();
	}

	/** @return array{indexKey:?string,datasetKeys:?non-empty-list<string>,accent:?non-empty-string} */
	protected function getInputDefaultsForOutput(): array {
		return [
			'indexKey'    => $this->collectedUsing?->indexKey,
			'datasetKeys' => empty( $items = $this->collectedUsing?->items ) ? null : array_values( $items ),
			'accent'      => $this->getAccentableTracer()?->getAccentOperationType()?->action(),
		];
	}

	protected function invalidateScraperCache( ?Closure $writeOutput ): void {
		$cacheStatusMsg = $this->scraper()->hasCache()
			? self::BEFORE_SCRAPE_INVALIDATION['pass']
			: self::BEFORE_SCRAPE_INVALIDATION['fail'];

		$writeOutput && $writeOutput( $cacheStatusMsg );

		$cacheClearedMsg = $this->scraper()->invalidateCache()
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
			$event->tracer->setIndicesSource( $this->collectedUsing = $collection );
		}
	}

	/** @return array<ArrayObject<array-key,TableColumnValue>> */
	protected function scrapeAndParseTableRows( bool $ignoreCache, ?Closure $outputWriter ): array {
		$ignoreCache && $this->invalidateScraperCache( $outputWriter );

		if ( $outputWriter ) {
			$outputWriter( PHP_EOL );
			$outputWriter( sprintf( self::FETCHING_CONTENT, ...$this->getScrapeSource() ) );
		}

		$scraper = $this->scraper();

		$this->setAccentOperationTypeFromInput();

		$scraper->getTracer()->addEventListener( $this->setIndicesSourceFromInput( ... ), structure: Table::Row );

		$actions = $this->getScraperActions( $outputWriter );
		$rows    = iterator_to_array( $this->tableFactory()->generateDataIterator( $scraper, $actions, $ignoreCache ) );

		$outputWriter && $outputWriter( sprintf( self::PARSING_FINISHED, $this->getTableContextForOutput() ) );

		$scraper->flush();

		return $rows;
	}

	/**
	 * @return array{
	 *  data : array<ArrayObject<array-key,TableColumnValue>>,
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
		return ( $scraper = $this->scraper() )->hasCache()
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
		return ( $t = $this->scraper()->getTracer() ) instanceof AccentedCharacter ? $t : null;
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
