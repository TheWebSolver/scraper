<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Fixture\Table;

use ReflectionClass;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Traits\Diacritic;
use TheWebSolver\Codegarage\Scraper\Event\TableTraced;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Traits\CollectorSource;
use TheWebSolver\Codegarage\Scraper\Traits\Table\HtmlTableFromNode;
use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedIndexableItem;

/** @template-implements TableTracer<string> */
class NodeTableTracerWithAccents implements TableTracer, AccentedIndexableItem {
	/** @use HtmlTableFromNode<string> */
	use HtmlTableFromNode, Diacritic, CollectorSource;

	/** @param list<string> $itemsNamesForTransliteration */
	public function __construct( protected array $itemsNamesForTransliteration = [] ) {
		$this->collectableFromAttribute( new ReflectionClass( $this ) );
		$this->addEventListener( Table::Row, $this->useCollectedKeysAsTableColumnIndices( ... ) );
	}

	public function indicesWithAccentedCharacters(): array {
		return $this->itemsNamesForTransliteration;
	}

	protected function useCollectedKeysAsTableColumnIndices( TableTraced $event ): void {
		$event->tracer->setItemsIndices( $this->collectSourceItems() );
	}
}
