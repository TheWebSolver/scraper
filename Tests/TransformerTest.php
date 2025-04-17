<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use DOMElement;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Error\ValidationFail;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Validatable;
use TheWebSolver\Codegarage\Scraper\Marshaller\MarshallItem;
use TheWebSolver\Codegarage\Scraper\Proxy\ItemValidatorProxy;
use TheWebSolver\Codegarage\Scraper\Decorator\HtmlEntityDecode;
use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedCharacter;
use TheWebSolver\Codegarage\Scraper\Decorator\TranslitAccentedItem;
use TheWebSolver\Codegarage\Scraper\Interfaces\AccentedIndexableItem;
use TheWebSolver\Codegarage\Scraper\Decorator\TranslitAccentedIndexableItem;

class TransformerTest extends TestCase {
	/** @param string|non-empty-list<mixed>|DOMElement $item */
	#[Test]
	#[DataProvider( 'provideItemMarshallerValues' )]
	public function itOnlyTransformsDOMElementOrArray(
		string|array|DOMElement $item,
		mixed $expected,
		bool $throws = false
	): void {
		$transformer = new MarshallItem();

		$throws && $this->expectException( InvalidSource::class );

		$this->assertSame( $expected, $transformer->transform( $item, $this->createStub( Transformer::class ) ) );
	}

	#[Test]
	public function itEncodesHtmlEntities(): void {
		$transformer = new HtmlEntityDecode( $base = $this->createMock( Transformer::class ) );

		$base->expects( $this->once() )
			->method( 'transform' )
			->willReturn( 'Develôper' );

		$this->assertSame(
			'Develôper',
			$transformer->transform( 'Devel&ocirc;per', $this->createStub( Transformer::class ) )
		);
	}

	/** @return mixed[] */
	public static function provideItemMarshallerValues(): array {
		return array(
			array( 'value', 'value' ),
			array( new DOMElement( 'div', 'div value' ), 'div value' ),
			array( array( 'full-node', 'nodeName', 'attributes', 'content', 'nodeNameClose' ), 'content' ),
			array( array(), 'throws exception coz not a normalized node', true ),
		);
	}

	/**
	 * @param class-string<TranslitAccentedItem<AccentedCharacter>|TranslitAccentedIndexableItem> $transformer
	 * @param class-string<AccentedIndexableItem|AccentedCharacter>                               $scopedClass
	 * @param ?array{0:string[],1:?string}                                                        $indices
	 */
	#[Test]
	#[DataProvider( 'provideTranslitSupportedScopes' )]
	public function itTranslitForProvidedScope(
		string $transformer,
		string $scopedClass,
		?array $indices = null,
		bool $throws = false
	): void {
		$base  = $this->createMock( Transformer::class );
		$scope = $this->createMock( $scopedClass );

		if ( ! $throws ) {
			$base->expects( $this->once() )
				->method( 'transform' )
				->willReturn( 'Develôper' );
		}

		$scope->expects( $this->once() )
			->method( 'getAccentOperationType' )
			->willReturn( AccentedCharacter::ACTION_TRANSLIT );

		$scope->expects( $this->once() )
			->method( 'getDiacriticsList' )
			->willReturn( array( 'ô' => 'o' ) );

		if ( $indices ) {
			[$keys, $key] = $indices;

			$scope->expects( $this->once() )
				->method( 'getItemsIndices' )
				->willReturn( $keys );

			$scope->expects( $this->once() )
				->method( 'getCurrentItemIndex' )
				->willReturn( $key );
		}

		if ( $throws ) {
			$this->expectException( InvalidSource::class );
		}

		$this->assertSame( 'Developer', ( new $transformer( $base ) )->transform( 'Devel&ocirc;per', $scope ) );
	}

	/** @return mixed[] */
	public static function provideTranslitSupportedScopes(): array {
		return array(
			array( TranslitAccentedItem::class, AccentedCharacter::class ),
			array(
				TranslitAccentedIndexableItem::class,
				AccentedIndexableItem::class,
				array( array( 'first', 'second' ), 'first' ),
			),
			array(
				TranslitAccentedIndexableItem::class,
				AccentedIndexableItem::class,
				array( array( 'first', 'second' ), null ),
				true,
			),
		);
	}

	#[Test]
	public function itUsesProxyTransformerToValidateTransitAndDecodeGivenString(): void {
		$validator = $this->createMockForIntersectionOfInterfaces(
			array( Validatable::class, AccentedIndexableItem::class )
		);

		$validator->expects( $this->once() )
			->method( 'getAccentOperationType' )
			->willReturn( AccentedCharacter::ACTION_TRANSLIT );

		$validator->expects( $this->once() )
			->method( 'getDiacriticsList' )
			->willReturn( array( 'ô' => 'o' ) );

		$validator->expects( $this->once() )
			->method( 'validate' )
			->with( 'Developer' )
			->willReturnCallback(
				fn( $data ) =>  ctype_alpha( $data ) ? $data : throw new ValidationFail( 'Given value is not Alpha.' )
			);

		$validator->expects( $this->once() )
			->method( 'getItemsIndices' )
			->willReturn( array( 'firstIndex', 'secondIndex' ) );

		$validator->expects( $this->once() )
			->method( 'getCurrentItemIndex' )
			->willReturn( 'firstIndex' );

		$validatorProxy = new ItemValidatorProxy();

		// @phpstan-ignore-next-line argument.type -- Generics not required for mocking.
		$this->assertSame( 'Developer', $validatorProxy->transform( 'Devel&ocirc;per', $validator ) );
	}
}
