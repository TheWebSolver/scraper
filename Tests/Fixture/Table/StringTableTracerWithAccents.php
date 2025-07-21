<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Fixture\Table;

use TheWebSolver\Codegarage\Scraper\Tracer\AccentedTableTracer;
use TheWebSolver\Codegarage\Scraper\Traits\Table\HtmlTableFromString;

/** @template-extends AccentedTableTracer<string> */
class StringTableTracerWithAccents extends AccentedTableTracer {
	/** @use HtmlTableFromString<string> */
	use HtmlTableFromString;

	/** @param list<string> $accentedItemIndices */
	public function __construct( protected array $accentedItemIndices = [] ) {
		parent::__construct();
	}
}
