<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper;

use Iterator;
use DOMElement;
use ArrayObject;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Marshaller\Marshaller;
use TheWebSolver\Codegarage\Scraper\Traits\TableNodeAware;
use TheWebSolver\Codegarage\Scraper\Interfaces\Collectable;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Traits\CollectionAware;
use TheWebSolver\Codegarage\Scraper\Marshaller\TableRowMarshaller;

/** @template-extends Scraper<array-key,array<string>> */
abstract class TableScraper extends Scraper {
	/** @use TableNodeAware<string,string> */
	use TableNodeAware, CollectionAware {
		TableNodeAware::flush as tableNodeFlush;
	}

	/**
	 * @param class-string<Collectable>                             $collectableClass
	 * @param Transformer<ArrayObject<array-key,string>|DOMElement> $trTransformer
	 * @param Transformer<string>                                   $tdTransformer
	 */
	public function __construct(
		private readonly string $collectableClass,
		Transformer $trTransformer = new TableRowMarshaller(),
		Transformer $tdTransformer = new Marshaller(),
		$sourceUrl = '',
	) {
		parent::__construct( $sourceUrl );

		$this->setCollectionItemsFrom( $collectableClass )
			->withAllTableNodes( scan: false )
			->useTransformers(
				array(
					'tr' => $trTransformer->with( $this->trParser( ... ) ),
					'td' => $tdTransformer->with( $this->tdParser( ... ) ),
				)
			);

		$this->useKeys( $this->getCollectableNames() );
	}

	/** @return ArrayObject<string,string> */
	public function trParser( string|DOMElement $element ): ArrayObject {
		$keys = $this->getCollectableNames();
		$set  = $this->tableDataSet( TableRowMarshaller::validate( $element ), $keys );
		$msg  = $this->collectableClass()::invalidCountMsg();

		count( $keys ) === $this->getCurrentTableRowCount()
			|| $this->throwError( $msg, count( $keys ), implode( '", "', $keys ) );

		return new ArrayObject( $set );
	}

	public function tdParser( string|DOMElement $element ): string {
		$content = trim( is_string( $element ) ? $element : $element->textContent );
		$item    = $this->getCurrentTableDataKey() ?? ''; // Always exists from row transformer.
		$value   = $this->isRequestedItem( $item ) ? $content : '';

		$value && $this->collectableClass()::validate( $value, $item, $this->throwError( ... ) );

		return $value;
	}

	public function parse( string $content ): Iterator {
		! empty( $this->getKeys() ) || $this->useKeys( $this->getCollectableNames() );

		$this->scanTableNodeIn( DOMDocumentFactory::createFromHtml( $content )->childNodes );

		$tableId = $this->getTableIds()[ array_key_last( $this->getTableIds() ) ];
		$data    = $this->getTableData()[ $tableId ] ?? throw ScraperError::trigger(
			'Could not find parsable content. ' . $this->getSource()->errorMsg()
		);

		$this->tableNodeFlush();

		foreach ( $data as $index => $arrayObject ) {
			$data = $arrayObject->getArrayCopy();

			yield $data[ $this->getIndexKey() ] ?? $index => $data;
		}

		unset( $data );
	}

	/** @return class-string<Collectable> */
	protected function collectableClass(): string {
		return $this->collectableClass;
	}

	protected function throwError( string $msg, string|int ...$args ): never {
		throw ScraperError::trigger( sprintf( $msg, ...$args ) . " {$this->getSource()->errorMsg()}" );
	}
}
