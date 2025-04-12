<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper;

use Iterator;
use TheWebSolver\Codegarage\Scraper\Traits\Diacritic;
use TheWebSolver\Codegarage\Scraper\SingleTableScraper;
use TheWebSolver\Codegarage\Scraper\Interfaces\KeyMapper;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Enums\Table as TableEnum;
use TheWebSolver\Codegarage\Scraper\Marshaller\TableRowMarshaller;
use TheWebSolver\Codegarage\Scraper\Marshaller\TableColumnTranslit;
use TheWebSolver\Codegarage\Scraper\Marshaller\TableColumnMarshaller;
use TheWebSolver\Codegarage\Scraper\Interfaces\SingleTableScraperWithAccent;

/**
 * @template TColumnReturn
 * @template-extends SingleTableScraper<TColumnReturn>
 * @template-implements SingleTableScraperWithAccent<TColumnReturn>
 */
abstract class AccentedSingleTableScraper extends SingleTableScraper implements SingleTableScraperWithAccent {
	use Diacritic;

	/** @var list<string> */
	protected array $transliterationColumnNames = array();

	/**
	 * Accepts transformers to validate and translit columns.
	 *
	 * If transformers not injected via constructor, then defaults are used.
	 * They only support collecting transformed `TColumnReturn` as a "string".
	 *
	 * @param ?Transformer<TColumnReturn> $validateRow    Uses `TableRowMarshaller` if not provided.
	 * @param ?Transformer<TColumnReturn> $translitColumn Uses `TableColumnTranslit` if not provided.
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
		[$row, $column] = $this->getInjectedOrDefaultTransformers();

		! empty( $this->transliterationColumnNames )
			&& ( $column = new TableColumnTranslit( $column, $this, $this->transliterationColumnNames ) );

		return $this->addTransformer( TableEnum::Row, $row )->addTransformer( TableEnum::Column, $column );
	}

	/** @return array{0:Transformer<TColumnReturn>,1:Transformer<TColumnReturn>} */
	protected function getInjectedOrDefaultTransformers(): array {
		$invalidCount = $this->getScraperSource()->name . ' ' . KeyMapper::INVALID_COUNT;

		return array(
			$this->validateRow ?? new TableRowMarshaller( $invalidCount, $this->getIndexKey() ),
			$this->translitColumn ?? new TableColumnMarshaller( $this->useCollectedKeys() ),
		);
	}
}
