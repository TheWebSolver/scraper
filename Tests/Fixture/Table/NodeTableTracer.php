<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Fixture\Table;

use TheWebSolver\Codegarage\Scraper\Tracer\TableTracer;
use TheWebSolver\Codegarage\Scraper\Traits\Table\HtmlTableFromNode;

/** @template-extends TableTracer<string> */
class NodeTableTracer extends TableTracer {
	/** @use HtmlTableFromNode<string> */
	use HtmlTableFromNode;
}
