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
	 * @param class-string<Collectable>                             $collectable
	 * @param Transformer<ArrayObject<array-key,string>|DOMElement> $rowTransformer
	 */
	public function __construct(
		string $collectable,
		Transformer $rowTransformer = new TableRowMarshaller(),
		$sourceUrl = '',
	) {
		$this->setCollectableNames( $collectable );

		parent::__construct( $sourceUrl );

		$this->useKeys( $this->getCollectableNames() );

		$this->withAllTableNodes( scan: false )
			->useTransformers(
				array( 'tr' => $rowTransformer->with( $this->tableRowParser( ... ) ) )
			);
	}

	/** @return ArrayObject<array-key,string> */
	public function tableRowParser( string|DOMElement $element ): ArrayObject {
		return new ArrayObject(
			$this->tableDataSet( TableRowMarshaller::validate( $element ), $this->getCollectableNames() )
		);
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
}
