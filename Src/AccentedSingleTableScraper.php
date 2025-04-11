<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper;

use Iterator;
use TheWebSolver\Codegarage\Scraper\Traits\Diacritic;
use TheWebSolver\Codegarage\Scraper\SingleTableScraper;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Enums\Table as TableEnum;
use TheWebSolver\Codegarage\Scraper\Marshaller\TableRowMarshaller;
use TheWebSolver\Codegarage\Scraper\Marshaller\TableColumnTranslit;
use TheWebSolver\Codegarage\Scraper\Marshaller\TableColumnMarshaller;
use TheWebSolver\Codegarage\Scraper\Interfaces\SingleTableScraperWithAccent;

/**
 * @template TValue
 * @template-extends SingleTableScraper<TValue>
 * @template-implements SingleTableScraperWithAccent<TValue>
 */
abstract class AccentedSingleTableScraper extends SingleTableScraper implements SingleTableScraperWithAccent {
	use Diacritic;

	/** @var list<string> */
	protected array $transliterationColumnNames = array();

	/**
	 * @param ?Transformer<TValue> $validateRow    Uses `TableRowMarshaller` if not provided.
	 * @param ?Transformer<TValue> $translitColumn Uses `TableColumnTranslit` if not provided.
	 * @no-named-arguments
	 */
	public function __construct(
		private ?Transformer $validateRow = null,
		private ?Transformer $translitColumn = null,
		string ...$transliterationColumnNames
	) {
		$this->transliterationColumnNames = $transliterationColumnNames;

		parent::__construct();
	}

	public function parse( string $content ): Iterator {
		yield from $this->withInjectedOrDefaultTransformers()->currentTableIterator( $content );
	}

	protected function withInjectedOrDefaultTransformers(): static {
		$countErrorMsg = $this->getCollectionSource()?->concrete::invalidCountMsg() ?? '';

		$this->addTransformer(
			TableEnum::Row,
			$this->validateRow ?? new TableRowMarshaller( $countErrorMsg, $this->getIndexKey() )
		);

		if ( $this->translitColumn ) {
			return $this->addTransformer( TableEnum::Column, $this->translitColumn );
		}

		if ( ! $keys = $this->useCollectedKeys() ) {
			return $this;
		}

		$td = new TableColumnMarshaller( $keys );

		! empty( $this->transliterationColumnNames )
			&& ( $td = new TableColumnTranslit( $td, $this, $this->transliterationColumnNames ) );

		return $this->addTransformer( TableEnum::Column, $td );
	}
}
