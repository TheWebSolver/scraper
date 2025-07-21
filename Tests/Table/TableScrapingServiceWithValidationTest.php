<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Table;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Test\Fixture\StripTags;
use TheWebSolver\Codegarage\Test\Fixture\DevDetails;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Validatable;
use TheWebSolver\Codegarage\Scraper\Attributes\CollectUsing;
use TheWebSolver\Codegarage\Scraper\Proxy\ItemValidatorProxy;
use TheWebSolver\Codegarage\Test\Fixture\Table\TableScrapingService;
use TheWebSolver\Codegarage\Test\Fixture\Table\NodeTableTracerWithAccents;
use TheWebSolver\Codegarage\Test\Fixture\Table\StringTableTracerWithAccents;

class TableScrapingServiceWithValidationTest extends TestCase {
	/** @param TableTracer<string> $tracer */
	#[Test]
	#[DataProvider( 'provideValidatableTableTracers' )]
	public function itValidatesScrapedValue( TableTracer $tracer, ?string $data = null, ?string $failed = null ): void {
		$service = new TableScrapingService( $tracer );

		// @phpstan-ignore-next-line
		$service->getTableTracer()->addTransformer( Table::Column, new ItemValidatorProxy( new StripTags() ) );

		$iterator = $service->parse( $data ?? TableScrapingServiceTest::getTableContent() );

		$failed && $this->expectExceptionMessage( sprintf( 'Failed validation of "%s".', $failed ) );

		$iterator->current();

		$this->assertTrue( $iterator->valid() );
	}

	/** @return mixed[] */ // phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.Missing
	public static function provideValidatableTableTracers(): array {
		return [
			[
				new #[CollectUsing( DevDetails::class )] class() extends StringTableTracerWithAccents implements Validatable {
					public function validate( mixed $data ): void {
						DevDetails::from( $this->getCurrentItemIndex() )->validate( $data ); // @phpstan-ignore-line
					}
				},
				null,
			],
			[
				new class() extends StringTableTracerWithAccents implements Validatable {
					public function __construct() {
						$this->setCollectorSource( new CollectUsing( DevDetails::class ) );
						parent::__construct();
					}

					public function validate( mixed $data ): void {
						DevDetails::from( $this->getCurrentItemIndex() )->validate( $data ); // @phpstan-ignore-line
					}
				},
				null,
			],
			[
				new #[CollectUsing( DevDetails::class )] class() extends StringTableTracerWithAccents implements Validatable {
					public function validate( mixed $data ): void {
						DevDetails::from( $this->getCurrentItemIndex() )->validate( $data ); // @phpstan-ignore-line
					}
				},
				'<table><tbody><tr><td>developer full-name more than 20 chars fails</td></tr></tbody></table>',
				'name',
			],
			[
				new #[CollectUsing( DevDetails::class )] class() extends StringTableTracerWithAccents implements Validatable {
					public function validate( mixed $data ): void {
						DevDetails::from( $this->getCurrentItemIndex() )->validate( $data ); // @phpstan-ignore-line
					}
				},
				'<table><tbody><tr><td>valid name</td><td>developer title more than 20 chars fails</td></tr></tbody></table>',
				'title',
			],
			[
				new #[CollectUsing( DevDetails::class )] class() extends StringTableTracerWithAccents implements Validatable {
					public function validate( mixed $data ): void {
						DevDetails::from( $this->getCurrentItemIndex() )->validate( $data ); // @phpstan-ignore-line
					}
				},
				'<table><tbody><tr><td>valid name</td><td>valid addr</td><td>address must be of 3 chars</td></tr></tbody></table>',
				'address',
			],
			[
				new #[CollectUsing( DevDetails::class )] class() extends StringTableTracerWithAccents implements Validatable {
					public function validate( mixed $data ): void {
						DevDetails::from( $this->getCurrentItemIndex() )->validate( $data ); // @phpstan-ignore-line
					}
				},
				'<table><tbody><tr><td>valid name</td><td>valid addr</td><td>yes</td><td>age must be 2 chars digit</td></tr></tbody></table>',
				'age',
			],
			[
				new #[CollectUsing( DevDetails::class )] class() extends StringTableTracerWithAccents implements Validatable {
					public function validate( mixed $data ): void {
						DevDetails::from( $this->getCurrentItemIndex() )->validate( $data ); // @phpstan-ignore-line
					}
				},
				'<table><tbody><tr><td>valid name</td><td>valid addr</td><td>yes</td><td>190</td></tr></tbody></table>',
				'age',
			],
			[
				new #[CollectUsing( DevDetails::class )] class() extends NodeTableTracerWithAccents implements Validatable {
					public function validate( mixed $data ): void {
						DevDetails::from( $this->getCurrentItemIndex() )->validate( $data ); // @phpstan-ignore-line
					}
				},
				null,
			],
			[
				new class() extends NodeTableTracerWithAccents implements Validatable {
					public function __construct() {
						$this->setCollectorSource( new CollectUsing( DevDetails::class ) );
						parent::__construct();
					}

					public function validate( mixed $data ): void {
						DevDetails::from( $this->getCurrentItemIndex() )->validate( $data ); // @phpstan-ignore-line
					}
				},
				null,
			],
			[
				new #[CollectUsing( DevDetails::class )] class() extends NodeTableTracerWithAccents implements Validatable {
					public function validate( mixed $data ): void {
						DevDetails::from( $this->getCurrentItemIndex() )->validate( $data ); // @phpstan-ignore-line
					}
				},
				'<table><tbody><tr><td>A very long developer full-name</td></tr></tbody></table>',
				'name',
			],
			[
				new #[CollectUsing( DevDetails::class )] class() extends NodeTableTracerWithAccents implements Validatable {
					public function validate( mixed $data ): void {
						DevDetails::from( $this->getCurrentItemIndex() )->validate( $data ); // @phpstan-ignore-line
					}
				},
				'<table><tbody><tr><td>valid name</td><td>developer title more than 20 chars fails</td></tr></tbody></table>',
				'title',
			],
			[
				new #[CollectUsing( DevDetails::class )] class() extends NodeTableTracerWithAccents implements Validatable {
					public function validate( mixed $data ): void {
						DevDetails::from( $this->getCurrentItemIndex() )->validate( $data ); // @phpstan-ignore-line
					}
				},
				'<table><tbody><tr><td>valid name</td><td>valid addr</td><td>developer address must be of 3 chars</td></tr></tbody></table>',
				'address',
			],
			[
				new #[CollectUsing( DevDetails::class )] class() extends NodeTableTracerWithAccents implements Validatable {
					public function validate( mixed $data ): void {
						DevDetails::from( $this->getCurrentItemIndex() )->validate( $data ); // @phpstan-ignore-line
					}
				},
				'<table><tbody><tr><td>valid name</td><td>valid addr</td><td>yes</td><td>age must be 2 chars digit</td></tr></tbody></table>',
				'age',
			],
			[
				new #[CollectUsing( DevDetails::class )] class() extends NodeTableTracerWithAccents implements Validatable {
					public function validate( mixed $data ): void {
						DevDetails::from( $this->getCurrentItemIndex() )->validate( $data ); // @phpstan-ignore-line
					}
				},
				'<table><tbody><tr><td>valid name</td><td>valid addr</td><td>yes</td><td>190</td></tr></tbody></table>',
				'age',
			],
		];
	}
}
