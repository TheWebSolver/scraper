<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Fixture;

use TheWebSolver\Codegarage\Scraper\Error\ValidationFail;

/** @template-implements \BackedEnum<string> */
enum DevDetails: string {
	case Name    = 'name';
	case Title   = 'title';
	case Address = 'address';
	case Age     = 'age';

	public function validate( string $data ): void {
		$isValid = match ( $this ) {
			DevDetails::Name, DevDetails::Title => strlen( $data ) < 20,
			DevDetails::Address                 => strlen( StripTags::from( $data ) ) === 3,
			DevDetails::Age                     => ctype_digit( $data ) && strlen( $data ) === 2,
		};

		$isValid || throw new ValidationFail( sprintf( 'Failed validation of "%s".', $this->value ) );
	}
}
