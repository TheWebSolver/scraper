<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper;

use Iterator;
use TheWebSolver\Codegarage\Scraper\Traits\Diacritic;
use TheWebSolver\Codegarage\Scraper\SingleTableScraper;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Marshaller\MarshallItem;
use TheWebSolver\Codegarage\Scraper\Enums\Table as TableEnum;
use TheWebSolver\Codegarage\Scraper\Marshaller\MarshallTableRow;
use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedIndexableItem;
use TheWebSolver\Codegarage\Scraper\Decorator\TranslitAccentedIndexableItem;

/**
 * @template TColumnReturn
 * @template-extends SingleTableScraper<TColumnReturn>
 */
abstract class AccentedSingleTableScraper extends SingleTableScraper implements AccentedIndexableItem {
	use Diacritic;

	/** @var non-empty-list<string|int> */
	protected array $transliterationColumnNames;

	/**
	 * Accepts transformers to validate and translit columns.
	 *
	 * If transformers not injected via constructor, then defaults are used.
	 * They only support collecting transformed `TColumnReturn` as a "string".
	 *
	 * @param ?Transformer<contravariant static,TColumnReturn> $rowTransformer    Uses `MarshallTableRow` if not provided.
	 * @param ?Transformer<contravariant static,TColumnReturn> $columnTransformer Uses `TranslitAccentedIndexableItem` if not provided.
	 * @no-named-arguments
	 */
	public function __construct(
		private ?Transformer $rowTransformer = null,
		private ?Transformer $columnTransformer = null,
		string|int ...$transliterationColumns
	) {
		empty( $transliterationColumns ) || $this->transliterationColumnNames = $transliterationColumns;

		parent::__construct();
	}

	public function parse( string $content ): Iterator {
		yield from $this->withInjectedOrDefaultTransformers()->currentTableIterator( $content );
	}

	public function indicesWithAccentedCharacters(): array {
		return $this->transliterationColumnNames ?? [];
	}

	protected function withInjectedOrDefaultTransformers(): static {
		[$row, $column] = $this->getInjectedOrDefaultTransformers();

		return $this->addTransformer( TableEnum::Row, $row )->addTransformer( TableEnum::Column, $column );
	}

	/** @return array{0:Transformer<contravariant static,TColumnReturn>,1:Transformer<contravariant static,TColumnReturn>} */
	protected function getInjectedOrDefaultTransformers(): array {
		$invalidCount = $this->getScraperSource()->name . ' ' . self::INVALID_COUNT;

		if ( ! $columnTransformer = $this->columnTransformer ) {
			$this->collectSourceItems();

			$columnTransformer = new MarshallItem();

			$this->indicesWithAccentedCharacters()
				&& ( $columnTransformer = new TranslitAccentedIndexableItem( $columnTransformer ) );
		}

		return [
			$this->rowTransformer ?? new MarshallTableRow( $invalidCount ),
			$columnTransformer,
		];
	}

	protected function hasDefaultTransformerProvided( TableEnum $for ): bool {
		return match ( $for ) {
			TableEnum::Row    => isset( $this->rowTransformer ),
			TableEnum::Column => isset( $this->columnTransformer ),
			default           => false,
		};
	}
}
