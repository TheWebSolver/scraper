<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Marshaller;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedCharacter;

/** @template-implements Transformer<TableTracer<string>,string> */
class TableColumnTranslit implements Transformer {
	/**
	 * @param Transformer<TableTracer<string>,string> $transformer       Base transformer which transforms column content.
	 * @param list<string>                            $targetColumnNames Column names to transit values of. If names not provided,
	 *                                                                   translit runs for each column (which might not be ideal).
	 */
	public function __construct(
		private readonly Transformer $transformer,
		private readonly AccentedCharacter $handler,
		private readonly ?array $targetColumnNames = null
	) {}

	public function transform( string|array|DOMElement $element, int $position, object $tracer ): string {
		$content     = $this->transformer->transform( $element, $position, $tracer );
		$targetNames = $this->targetColumnNames ?? $tracer->getColumnNames();
		$currentCol  = $tracer->getCurrentColumnName();

		if ( ! $this->shouldTranslit() || ( $currentCol && ! in_array( $currentCol, $targetNames, strict: true ) ) ) {
			return $content;
		}

		$characters = $this->handler->getDiacriticsList();

		return str_replace( array_keys( $characters ), array_values( $characters ), $content );
	}

	private function shouldTranslit(): bool {
		return AccentedCharacter::ACTION_TRANSLIT === $this->handler->getAccentOperationType()
			&& ! empty( $this->handler->getDiacriticsList() );
	}
}
