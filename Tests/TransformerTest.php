<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Scraper\Error\ValidationFail;
use TheWebSolver\Codegarage\Scraper\Interfaces\Validatable;
use TheWebSolver\Codegarage\Scraper\Proxy\ItemValidatorProxy;
use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedIndexableTracer;

class TransformerTest extends TestCase {
	#[Test]
	public function itUsesProxyTransformerToValidateTransitAndDecodeGivenString(): void {
		$validator = $this->createMockForIntersectionOfInterfaces(
			array( Validatable::class, AccentedIndexableTracer::class )
		);

		$validator->expects( $this->once() )
			->method( 'getAccentOperationType' )
			->willReturn( AccentedIndexableTracer::ACTION_TRANSLIT );

		$validator->expects( $this->exactly( 2 ) )
			->method( 'getDiacriticsList' )
			->willReturn( array( 'ô' => 'o' ) );

		$validator->expects( $this->once() )
			->method( 'validate' )
			->with( 'Developer' )
			->willReturnCallback(
				fn( $data ) =>  ctype_alpha( $data ) ? $data : throw new ValidationFail( 'Given value is not Alpha.' )
			);

		$validatorProxy = new ItemValidatorProxy();

		// @phpstan-ignore-next-line argument.type -- Generics not required with nock.
		$this->assertSame( 'Developer', $validatorProxy->transform( 'Devel&ocirc;per', $validator ) );
	}
}
