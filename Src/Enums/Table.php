<?php
declare( strict_types=1 );

namespace TheWebSolver\Codegarage\Scraper\Enums;

/** @template-implements BackedEnum<string> */
enum Table: string {
	case THead   = 'thead';
	case TBody   = 'tbody';
	case Caption = 'caption';
	case Head    = 'th';
	case Row     = 'tr';
	case Column  = 'td';

	/** @placeholder: **%s:** The case name. */
	final public const TRACEABLE_STRUCTURE = self::class . '::%s must always be traced.';
	/** @placeholder: **%s:** The case name. */
	final public const NON_DISPATCHABLE_EVENT = self::class . '::%s event does not support event listener.';
	/** @placeholder: **%s:** The case name. */
	final public const NON_STOPPABLE_EVENT = self::class . '::%s event does not support tracing to be stopped.';

	public function untraceable(): bool {
		return match ( $this ) {
			default                    => false,
			self::THead, self::Caption => true,
		};
	}

	public function eventDispatchable(): bool {
		return match ( $this ) {
			default      => true,
			self::Column => false,
		};
	}

	public function eventStoppable(): bool {
		return match ( $this ) {
			default       => true,
			self::Caption => false,
		};
	}
}
