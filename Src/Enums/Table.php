<?php
declare( strict_types=1 );

namespace TheWebSolver\Codegarage\Scraper\Enums;

enum Table: string {
	case THead   = 'thead';
	case TBody   = 'tbody';
	case Caption = 'caption';
	case Head    = 'th';
	case Row     = 'tr';
	case Column  = 'td';
}
