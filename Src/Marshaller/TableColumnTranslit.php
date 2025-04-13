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

	public function transform( string|array|DOMElement $element, object $tracer ): string {
		$content     = $this->transformer->transform( $element, $tracer );
		$targetNames = $this->targetColumnNames ?? $tracer->getColumnNames();
		$name        = $tracer->getCurrentColumnName();

		if ( ! $this->shouldTranslit() || ( $name && ! in_array( $name, $targetNames, strict: true ) ) ) {
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
