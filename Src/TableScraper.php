<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper;

use Iterator;
use DOMElement;
use ArrayObject;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
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
	 * @param Transformer<ArrayObject<array-key,string>|DOMElement> $rowTransformer
	 */
	public function __construct(
		private readonly string $collectableClass,
		Transformer $rowTransformer = new TableRowMarshaller(),
		$sourceUrl = '',
	) {
		parent::__construct( $sourceUrl );

		$this->setCollectionItemsFrom( $collectableClass )
			->withAllTableNodes( scan: false )
			->useTransformers( array( 'tr' => $rowTransformer->with( $this->tableRowParser( ... ) ) ) );

		$this->useKeys( $this->getCollectableNames() );
	}

	/** @return ArrayObject<string,string> */
	public function tableRowParser( string|DOMElement $element ): ArrayObject {
		$keys = $this->getCollectableNames();
		$set  = $this->tableDataSet( TableRowMarshaller::validate( $element ), $keys );

		$this->ensureAllKeysExistsInCollection( $set );

		$set = $this->withRequestedKeys( $set );

		array_walk( $set, $this->collectableClass::validate( ... ), $this->throwError( ... ) );

		return new ArrayObject( $set );
	}

	public function parse( string $content ): Iterator {
		! empty( $this->getKeys() ) || $this->useKeys( $this->getCollectableNames() );

		$this->scanTableNodeIn( DOMDocumentFactory::createFromHtml( $content )->childNodes );

		$tableId = $this->getTableIds()[ array_key_last( $this->getTableIds() ) ];
		$data    = $this->getTableData()[ $tableId ] ?? throw ScraperError::trigger(
			'Could not find parsable content. ' . $this->getSource()->errorMsg()
		);

		$this->tableNodeFlush();

		foreach ( $data as $arrayObject ) {
			yield $arrayObject->getArrayCopy();
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

	/**
	 * @param string[] $set
	 * @phpstan-assert array<string,string> $set
	 */
	private function ensureAllKeysExistsInCollection( array $set ): void {
		$msg = $this->collectableClass()::invalidCountMsg();

		empty( array_diff_key( $keys = $this->getCollectableNames(), $set ) )
			|| $this->throwError( $msg, count( $keys ), implode( '", "', $keys ) );
	}
}
