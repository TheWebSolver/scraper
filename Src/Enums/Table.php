<?php
declare( strict_types=1 );

namespace TheWebSolver\Codegarage\Scraper\Enums;

enum Table {
	case Caption;
	case Head;
	case Body;
	case Row;
	case Column;
}
