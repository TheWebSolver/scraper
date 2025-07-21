<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Fixture\Table;

use TheWebSolver\Codegarage\Scraper\Tracer\AccentedTableTracer;
use TheWebSolver\Codegarage\Scraper\Traits\Table\HtmlTableFromNode;

/** @template-extends AccentedTableTracer<string> */
class NodeTableTracerWithAccents extends AccentedTableTracer {
	/** @use HtmlTableFromNode<string> */
	use HtmlTableFromNode;

	/** @param list<string> $accentedItemIndices */
	public function __construct( protected array $accentedItemIndices = [] ) {
		parent::__construct();
	}
}
