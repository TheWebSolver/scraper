<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;

/** @template TReturn */
interface Transformer {
	public const COLLECT_HTML = 'html';
	public const COLLECT_NODE = 'node';

	/**
	 * Returns the collectable data after transformation.
	 *
	 * @return array<self::COLLECT_*,bool>
	 */
	public function collectables(): array;

	/**
	 * Returns the transformed contents.
	 *
	 * @return array<int,TReturn>
	 */
	public function getContent(): array;

	/**
	 * Sets whether full HTML content should be collected.
	 */
	public function collectHtml(): void;

	/**
	 * Sets whether the current DOM Element should be collected.
	 */
	public function collectElement(): void;

	/**
	 * Transforms the element to be collected using the provided marshaller.
	 *
	 * @param callable(string|DomElement): TReturn $callback
	 */
	public function marshallWith( callable $callback ): void;

	/**
	 * Collects data based on collection datatype set.
	 *
	 * @return ($onlyContent is true ? TReturn : array{0:TReturn,1?:string,2?:DomElement})
	 * @throws InvalidSource When $element is string and cannot be inferred to DOMElement.
	 */
	public function collect( string|DOMElement $element, bool $onlyContent = false ): mixed;

	public function flushContent(): void;
}
