<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Fixture;

/** @template-implements \BackedEnum<string> */
enum DevDetails: string {
	case Name    = 'name';
	case Title   = 'title';
	case Address = 'address';
	case Age     = 'age';
}
