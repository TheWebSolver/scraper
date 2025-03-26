<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper;

use Closure;
use Iterator;
use DOMElement;
use ArrayObject;
use TheWebSolver\Codegarage\Scraper\Traits\ScrapeYard;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Interfaces\Scrapable;
use TheWebSolver\Codegarage\Scraper\Traits\ScraperSource;
use TheWebSolver\Codegarage\Scraper\Traits\TableNodeAware;
use TheWebSolver\Codegarage\Scraper\Interfaces\Collectable;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Traits\CollectionAware;

/**
 * @template TdReturn
 * @template-implements Scrapable<array-key,ArrayObject<array-key,TdReturn>>
 * @template-implements TableTracer<string,TdReturn>
 */
abstract class SingleTableScraper implements Scrapable, TableTracer {
	/** @use TableNodeAware<string,TdReturn> */
	use ScrapeYard, ScraperSource, TableNodeAware, CollectionAware;

	private Closure $unsubscribeError;

	/** @param class-string<Collectable> $collectableClass */
	public function __construct( private readonly string $collectableClass ) {
		$this->sourceFromAttribute()
			->withCachePath( $this->defaultCachePath(), $this->getSource()->filename )
			->setCollectionItemsFrom( $collectableClass )
			->useKeys( $columnNames = $this->getCollectableNames() )
			->subscribeWith( static fn( $i ) => $i->withAllTables( false )->setColumnNames( $columnNames, $i->getTableId( true ) ) );

		$this->unsubscribeError = ScraperError::for( $this->getSource() );
	}

	public function tdParser( string|DOMElement $element ): string {
		$content = trim( is_string( $element ) ? $element : $element->textContent );
		$item    = $this->getCurrentColumnName() ?? ''; // Always exists from row transformer.
		$value   = $this->isRequestedItem( $item ) ? $content : '';

		$value && $this->collectableClass()::validate( $value, $item, ScraperError::withSourceMsg( ... ) );

		return $value;
	}

	public function parse( string $content ): Iterator {
		yield from $this->validateCurrentTableParsedData( $content );

		$this->flushTransformers();
	}

	public function flush(): void {
		( $this->unsubscribeError )();
	}

	/** @return class-string<Collectable> */
	protected function collectableClass(): string {
		return $this->collectableClass;
	}

	/** @return Iterator<string|int,ArrayObject<array-key,TdReturn>> */
	protected function validateCurrentTableParsedData( string $content ): Iterator {
		! empty( $this->getKeys() ) || $this->useKeys( $this->getCollectableNames() );

		$this->traceTableIn( DOMDocumentFactory::createFromHtml( $content )->childNodes );

		$data = $this->getTableData()[ $this->getTableId( current: true ) ]
			?? ScraperError::withSourceMsg( 'Could not find parsable content.' );

		$this->flushDiscoveredContents();

		return $data;
	}
}
