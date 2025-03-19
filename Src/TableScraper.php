<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper;

use Iterator;
use DOMElement;
use ArrayObject;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Traits\TableNodeAware;
use TheWebSolver\Codegarage\Scraper\Interfaces\Collectable;
use TheWebSolver\Codegarage\Scraper\Traits\CollectionAware;
use TheWebSolver\Codegarage\Scraper\Marshaller\TableRowMarshaller;

/** @template-extends Scraper<array-key,array<string>> */
abstract class TableScraper extends Scraper {
	/** @use TableNodeAware<string,string> */
	use TableNodeAware, CollectionAware;

	/** @param class-string<Collectable> $collectable */
	public function __construct(
		string $collectable,
		$sourceUrl = '',
		string $dirPath = '',
		string $filename = ''
	) {
		$this->setCollectableNames( $collectable );

		parent::__construct( $sourceUrl, $dirPath, $filename );

		$this->useKeys( $this->getCollectableNames() );
	}

	/** @return ArrayObject<array-key,string> */
	public function tableRowParser( string|DOMElement $element ): ArrayObject {
		return new ArrayObject(
			$this->tableDataSet( TableRowMarshaller::validate( $element ), $this->getCollectableNames() )
		);
	}

	public function parse( string $content ): Iterator {
		! empty( $this->getKeys() ) || $this->useKeys( $this->getCollectableNames() );

		$rowTransformer = new TableRowMarshaller();

		$rowTransformer->with( $this->tableRowParser( ... ) );

		$this->useTransformers(
			array( 'tr' => $rowTransformer )
		);

		$this->withAllTableNodes( scan: false )
			->scanTableNodeIn( DOMDocumentFactory::createFromHtml( $content )->childNodes );

		$tableId = $this->getTableIds()[ array_key_last( $this->getTableIds() ) ];
		$data    = $this->getTableData()[ $tableId ] ?? throw ScraperError::trigger(
			'Could not find parsable content. ' . $this->getSource()->errorMsg()
		);

		$this->flush();

		foreach ( $data as $arrayObject ) {
			yield $arrayObject->getArrayCopy();
		}
	}
}
