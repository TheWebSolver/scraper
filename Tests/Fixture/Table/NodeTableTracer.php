<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Fixture\Table;

use TheWebSolver\Codegarage\Scraper\Attributes\CollectFrom;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Traits\Table\HtmlTableFromNode;

/** @template-implements TableTracer<string> */
class NodeTableTracer implements TableTracer {
	/** @use HtmlTableFromNode<string> */
	use HtmlTableFromNode;

	public function getCollectorSource(): ?CollectFrom {
		return null;
	}
}
