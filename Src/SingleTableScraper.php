<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper;

use Closure;
use Iterator;
use DOMElement;
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
 * @template-implements Scrapable<string,array<TdReturn>>
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

		$value && $this->collectableClass()::validate( $value, $item, $this->throwError( ... ) );

		return $value;
	}

	public function parse( string $content ): Iterator {
		! empty( $this->getKeys() ) || $this->useKeys( $this->getCollectableNames() );

		$this->traceTableIn( DOMDocumentFactory::createFromHtml( $content )->childNodes );

		$tableId = $this->getTableId()[ array_key_last( $this->getTableId() ) ];
		$data    = $this->getTableData()[ $tableId ] ?? throw ScraperError::trigger(
			'Could not find parsable content. ' . $this->getSource()->errorMsg()
		);

		$this->flushTableNodeTrace();

		foreach ( $data as $index => $arrayObject ) {
			$data = $arrayObject->getArrayCopy();

			// FIXME: $data[$indexKey] might not always be string.
			yield $data[ $this->getIndexKey() ] ?? $index => $data; // @phpstan-ignore-line
		}

		unset( $data );
	}

	public function flush(): void {
		( $this->unsubscribeError )();
	}

	/** @return class-string<Collectable> */
	protected function collectableClass(): string {
		return $this->collectableClass;
	}

	protected function throwError( string $msg, string|int ...$args ): never {
		throw ScraperError::trigger( sprintf( $msg, ...$args ) . " {$this->getSource()->errorMsg()}" );
	}
}
