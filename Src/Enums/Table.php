<?php
declare( strict_types=1 );

namespace TheWebSolver\Codegarage\Scraper\Enums;

enum Table: string {
	case Caption = 'caption';
	case Head    = 'th';
	case Body    = 'tbody';
	case Row     = 'tr';
	case Column  = 'td';
}
