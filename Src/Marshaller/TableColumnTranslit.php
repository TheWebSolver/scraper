<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Marshaller;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

/** @template-implements Transformer<string> */
class TableColumnTranslit implements Transformer {
	/**
	 * @param Transformer<string>  $transformer       Base transformer which transforms column content.
	 * @param array<string,string> $translitPair      Diacritics with search as key & replacement as value.
	 * @param list<string>         $targetColumnNames Column names to transit values of. If names not provided,
	 *                                                translit runs for each column (which might not be ideal).
	 */
	public function __construct(
		private readonly Transformer $transformer,
		private readonly array $translitPair,
		private readonly ?array $targetColumnNames = null
	) {}

	public function transform( string|DOMElement $element, int $position, TableTracer $tracer ): string {
		$content = $this->transformer->transform( $element, $position, $tracer );

		if ( empty( $this->translitPair ) ) {
			return $content;
		}

		$targetNames = $this->targetColumnNames ?? $tracer->getColumnNames();

		return ! in_array( $tracer->getCurrentColumnName(), $targetNames, strict: true )
			? $content
			: str_replace(
				search: array_keys( $this->translitPair ),
				replace: array_values( $this->translitPair ),
				subject: $content
			);
	}
}
