<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Fixture\Table;

use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Traits\Table\HtmlTableFromString;

/** @template-implements TableTracer<string> */
class StringTableTracer implements TableTracer {
	/** @use HtmlTableFromString<string> */
	use HtmlTableFromString;
}
