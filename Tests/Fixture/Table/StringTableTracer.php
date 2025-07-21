<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Fixture\Table;

use TheWebSolver\Codegarage\Scraper\Tracer\TableTracer;
use TheWebSolver\Codegarage\Scraper\Traits\Table\HtmlTableFromString;

/** @template-extends TableTracer<string> */
class StringTableTracer extends TableTracer {
	/** @use HtmlTableFromString<string> */
	use HtmlTableFromString;
}
