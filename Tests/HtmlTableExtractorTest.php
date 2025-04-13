<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use Closure;
use DOMNode;
use DOMElement;
use ArrayObject;
use LogicException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\AssertDOMElement;
use TheWebSolver\Codegarage\Scraper\Helper\Normalize;
use TheWebSolver\Codegarage\Scraper\DOMDocumentFactory;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Marshaller\TableRowMarshaller;
use TheWebSolver\Codegarage\Scraper\Traits\Table\HtmlTableFromNode;
use TheWebSolver\Codegarage\Scraper\Traits\Table\HtmlTableFromString;

class HtmlTableExtractorTest extends TestCase {
	final public const TABLE_SOURCE = __DIR__ . DIRECTORY_SEPARATOR . 'Resource' . DIRECTORY_SEPARATOR . 'table.html';

	private function getTableFromSource(): string {
		return file_get_contents( self::TABLE_SOURCE ) ?: '';
	}

	#[Test]
	public function itOnlyScansTargetedTable(): void {
		$nodeScanner = new DOMNodeScanner( fn( $node ) => AssertDOMElement::hasId( $node, 'inner-content-table' ) );

		$nodeScanner->inferTableFrom( $source = $this->getTableFromSource() );

		$this->assertCount( 1, $tableIds = $nodeScanner->getTableId() );
		$this->assertSame(
			array( '1: First Data', '2: Second Data' ),
			$nodeScanner->getTableData()[ $tableIds[0] ]->current()->getArrayCopy()
		);

		$targetedString = static fn ( string $node )
			=> str_contains( explode( '<div id="inner-content"', $node, 2 )[0], 'inner-content-table' );

		$stringScanner = new DOMStringScanner( $targetedString );

		$stringScanner->inferTableFrom( $source );
		$tableIds = $stringScanner->getTableId();

		$this->assertCount( 1, $tableIds, 'Target validation does not apply. First table found.' );
		$this->assertSame( 'John Doe', $stringScanner->getTableData()[ $tableIds[0] ]->current()['Name'] );

		// Cannot target multiple table with string scanner.
		$this->expectException( ScraperError::class );
		$stringScanner->withAllTables( true );
	}

	#[Test]
	public function itOnlyScansTargetedTableWithProvidedColumnNames(): void {
		$nodeScanner   = new DOMNodeScanner( fn ( $node ) => AssertDOMElement::hasId( $node, id: 'developer-list' ) );
		$stringScanner = new DOMStringScanner( /* Validator does nothing for string scanner. */ );

		foreach ( array( $nodeScanner, $stringScanner ) as $scanner ) {
			$scanner->addEventListener(
				Table::TBody,
				fn( $s ) => $s->setColumnNames( array( 'name', 'title' ), $s->getTableId( true ) )
			);

			$scanner->inferTableFrom( $source = $this->getTableFromSource() );

			$this->assertSame(
				array(
					'name'  => 'John Doe',
					'title' => 'PHP Developer',
				),
				$scanner->getTableData()[ $scanner->getTableId( true ) ]->current()->getArrayCopy()
			);
		}

		$nodeScanner = new class() extends DOMNodeScanner {
			protected function isTableColumnStructure( mixed $node ): bool {
				return parent::isTableColumnStructure( $node )
					&& $node instanceof DOMNode
					&& ! str_ends_with( $node->firstChild->textContent ?? '', 'Developer' );
			}
		};

		$stringScanner = new class() extends DOMStringScanner {
			protected function isTableColumnStructure( mixed $node ): bool {
				return parent::isTableColumnStructure( $node )
					&& is_array( $node ) && is_string( $node[3] ?? null )
					&& ! str_ends_with( $node[3], 'Developer' );
			}
		};

		foreach ( array( $nodeScanner, $stringScanner ) as $scanner ) {
			$scanner->addEventListener(
				Table::TBody,
				fn( $scanner ) => $scanner->setColumnNames( array( 'name', 'address' ), $scanner->getTableId( true ) )
			);

			$scanner->inferTableFrom( $source );

			$id     = $scanner->getTableId( true );
			$actual = $scanner->getTableData()[ $id ]->current()->getArrayCopy();

			$this->assertCount( 2, $actual );
			$this->assertSame( 'John Doe', reset( $actual ) );
			$this->assertSame( 'Ktm', $scanner instanceof DOMNodeScanner ? end( $actual ) : strip_tags( end( $actual ) ?: '' ) );
		}
	}

	/**
	 * @param list<string> $columnNames
	 * @param list<int>    $offset
	 * @param mixed[]      $expected
	 */
	#[Test]
	#[DataProvider( 'provideTableColumnDataWithOffset' )]
	public function itOffsetsInBetweenIndicesOfColumnNames( array $columnNames, array $offset, array $expected ): void {
		$source = '<table><tbody><tr><td>0</td><td>1</td><td>2</td><td>3</td><td>4</td></tr></tbody></table>';

		foreach ( array( new DOMNodeScanner(), new DOMStringScanner() ) as $scanner ) {
			$scanner->addEventListener(
				Table::TBody,
				fn( $scanner ) => $scanner->setColumnNames( $columnNames, $scanner->getTableId( true ), ...$offset )
			);

			$tr = new TableRowMarshaller( 'Should Not Throw exception' );

			// @phpstan-ignore-next-line -- Ignore $tr generic type.
			$scanner->addTransformer( Table::Row, $tr )->inferTableFrom( $source );

			$this->assertSame(
				$expected,
				$scanner->getTableData()[ $scanner->getTableId( true ) ]->current()->getArrayCopy()
			);
		}
	}

	/** @return mixed[] */
	// phpcs:disable WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	public static function provideTableColumnDataWithOffset(): array {
		return array(
			array( array( 'zero', 'two' ), array( 1 ), array( 'zero' => '0', 'two' => '2' ) ),
			array( array( 'one', 'three' ), array( 0, 2 ), array( 'one' => '1', 'three' => '3' ) ),
			array( array( 'zero', 'three', 'four' ), array( 1, 2 ), array( 'zero' => '0', 'three' => '3', 'four' => '4' ) ),
		);
	}

	/**
	 * @param mixed[]             $args
	 * @param TableTracer<string> $scanner
	 */
	#[Test]
	#[DataProvider( 'provideMethodsThatThrowsException' )]
	public function itThrowsExceptionWhenInvokedBeforeTableFound( string $methodName, array $args, TableTracer $scanner ): void {
		$this->expectException( ScraperError::class );
		$this->expectExceptionMessage( sprintf( DOMNodeScanner::USE_EVENT_DISPATCHER, $scanner::class, $methodName, '' ) );

		$scanner->{$methodName}( ...$args );
	}

	/** @return mixed[] */
	public static function provideMethodsThatThrowsException(): array {
		return array(
			array( 'setColumnNames', array( array(), 0 ), new DOMNodeScanner() ),
			array( 'setColumnNames', array( array(), 0 ), new DOMStringScanner() ),
		);
	}

	#[Test]
	public function itGetsTheTargetedTableNode(): void {
		$domScanner   = new DOMNodeScanner();
		$thMarshaller = new /** @template-implements Transformer<DOMNodeScanner|DOMStringScanner,string> */ class()
		implements Transformer {
			public function transform( string|array|DOMElement $element, int $position, object $tracer ): string {
				$content = $element instanceof DOMElement ? $element->textContent : $element;

				TestCase::assertIsString( $content );

				return explode( '[', strip_tags( html_entity_decode( $content ) ) )[0];
			}
		};

		$tdMarshaller = new /** @template-implements Transformer<DOMNodeScanner|DOMStringScanner,string> */ class()
			implements Transformer {
			public function transform( string|array|DOMElement $element, int $position, object $tracer ): string {
				if ( $element instanceof DOMElement ) {
					TestCase::assertInstanceOf( DOMNodeScanner::class, $tracer );

					$content = $element->textContent;
				} else {
					TestCase::assertInstanceOf( DOMStringScanner::class, $tracer );
					TestCase::assertIsString( $content = $element[3] );

					$content = strip_tags( $content );
				}

				return trim( $content );
			}
		};

		foreach ( array( new DOMStringScanner(), $domScanner ) as $scanner ) {
			$scanner
				->addTransformer( Table::Head, $thMarshaller )
				->addTransformer( Table::Column, $tdMarshaller )
				->withAllTables()
				->inferTableFrom( $this->getTableFromSource() );

			$ids                  = $scanner->getTableId();
			$th                   = $scanner->getTableHead()[ $ids[0] ]->toArray();
			$data                 = $scanner->getTableData();
			$devTable             = $data[ $ids[0] ];
			$firstTableColumNames = array( 'Name', 'Title', 'Address' );

			$this->assertCount( $scanner instanceof DOMNodeScanner ? 2 : 1, $scanner->getTableId() );

			$this->assertSame( $firstTableColumNames, $th );
			$this->assertEqualsCanonicalizing( array_keys( $first = $devTable->current()->getArrayCopy() ), $th );
			$this->assertSame(
				array( 'John Doe', 'PHP Developer', 'Ktm' ),
				array_values( $first ),
				$scanner::class . ' -> First row dataset of first table matches'
			);

			$scanner->addEventListener(
				Table::TBody,
				static fn( $i ) => $i->setColumnNames( array( 'finalAddress' ), $i->getTableId( true ) )
			);

			$devTable->next();

			$this->assertCount(
				$scanner instanceof DOMNodeScanner ? 3 : 1,
				$scanner->getTableId(),
				$scanner::class . ' -> Nested table inside third <tr> of first table is discoverable by DOMNodeScanner.'
			);
			$this->assertSame(
				$scanner instanceof DOMNodeScanner ? array( 'finalAddress' ) : $firstTableColumNames,
				$scanner->getColumnNames(),
				$scanner::class . ' -> Column name of nested table inside third <tr> of first table has one column discoverable by DOMNodeScanner'
			);

			$this->assertSame(
				array( 'Lorem Ipsum', 'JS Developer', 'Bkt' ),
				array_values( $devTable->current()->getArrayCopy() ),
				'Mapped values so DOMStringScanner values tags are stripped'
			);
		}//end foreach

		// These can only be discovered by DOMNodeScanner.
		$domTableIds = $domScanner->getTableId();
		$dataTable   = $domScanner->getTableData()[ $domTableIds[1] ];

		$this->assertSame(
			array( '1: First Data', '2: Second Data' ),
			array_values( $dataTable->current()->getArrayCopy() )
		);

		$addressTableId = $domScanner->getTableId( true );

		$this->assertSame( $addressTableId, end( $domTableIds ) );
		$this->assertSame(
			'Bkt',
			$domScanner->getTableData()[ $addressTableId ]->current()->getArrayCopy()['finalAddress']
		);
	}

	#[Test]
	public function itScrapesDataFromTableHeadAndBodyElement(): void {
		$source = '
		<table>
			  <caption>This is a test with Table Head</caption>

				<thead> <!-- This is a comment. -->
				 <tr>
				<!-- This is a comment. -->
					   <th>Title</th>
				   <!-- This is a comment. -->
						 <th>  Another
						 Title </th>
						   </tr>
					</thead>

					<tbody>
					<!-- This is a comment. -->
					<tr><th>Heading 1</th>
					<!-- This is a comment. -->
					<td>Value One</td>
					<!-- This is a comment. -->
					</tr>
					<!-- This is a comment. -->
					<tr><th>Heading 2</th><td>     Value 				Two   </td></tr>
					</tbody>

				</table>
		';

		foreach ( array( new DOMNodeScanner(), new DOMStringScanner() ) as $scanner ) {
			$scanner->inferTableFrom( $source );

			$tableIds  = $scanner->getTableId();
			$fixedHead = $scanner->getTableHead()[ $tableIds[0] ];
			$data      = $scanner->getTableData()[ $tableIds[0] ];

			$this->assertCount( 1, $tableIds );
			$this->assertSame( $headers = array( 'Title', 'Another Title' ), $fixedHead->toArray() );

			foreach ( $data as $index => $tableData ) {
				$value = 0 === $index ? array( 'Heading 1', 'Value One' ) : array( 'Heading 2', 'Value Two' );

				$this->assertSame( $headers, array_keys( $arrayCopy = $tableData->getArrayCopy() ) );
				$this->assertSame( $value, array_values( $arrayCopy ) );
			}

			$this->assertSame( 2, (int) ( $index ?? 0 ) + 1 );

			$scanner->resetTableTraced();

			$this->assertCount( 1, $scanner->getTableId(), 'Table ID will not be reset' );
			$this->assertEmpty( $scanner->getTableData() );
		}//end foreach

		foreach ( array( new DOMNodeScanner(), new DOMStringScanner() ) as $scanner ) {
			$scanner->traceWithout( Table::THead )->inferTableFrom( $source );

			$this->assertEmpty( $scanner->getTableHead() );

			$data = $scanner->getTableData()[ $scanner->getTableId( true ) ];

			$copy = $data->current()->getArrayCopy();

			// TODO: maybe use ::flush() and check if head is used as column names when traceWithout is used.
			$this->assertIsList( $copy );
		}
	}

	#[Test]
	#[DataProvider( 'provideInvalidHtmlTable' )]
	public function itParsesInvalidTableGracefully( string $source, bool $hasHead = false ): void {
		$scanner = new DOMNodeScanner();

		foreach ( array( new DOMNodeScanner(), new DOMStringScanner() ) as $scanner ) {
			$scanner->inferTableFrom( $source );

			$this->assertEmpty( $scanner->getTableData() );

			if ( $hasHead ) {
				$this->assertNotEmpty( $scanner->getTableHead() );
			} else {
				$this->assertEmpty( $scanner->getTableHead() );
			}
		}
	}

	/** @return mixed[] */
	public static function provideInvalidHtmlTable(): array {
		return array(
			array( '<table><thead><!-- only heads --><tr><th>head</th></tr></thead></table>' ),
			array( '<table><tbody><!-- only heads --><tr><th>head</th></tr></tbody></table>', true ),
			array( '<table><tbody><!-- empty rows --><tr></tr><tr></tr></tbody></table>' ),
			array( '<table><tbody><!-- no rows --></tbody></table>' ),
			array( '<table><tbody id="no-childNodes"></tbody></table>' ),
			array( '<table></table>' ),
		);
	}

	#[Test]
	public function itDoesNotCollectValueIfTransformerReturnsFalsyValue(): void {
		$scanner = new DOMNodeScanner();
		$source  = '
			<table>
				<thead><tr><th>First</th><th>Last</th></tr></thead>
				<tbody>
					<tr>
						<-- will not be ignored -->
						<-- non-transformed value -->
						<td>Value One</td>
						<-- will be ignored -->
						<td>Value Two</td>
					</tr>
				</tbody>
			</table>
		';

		$tdMarshaller = new /** @template-implements Transformer<DOMNodeScanner|DOMStringScanner,string> */ class()
		implements Transformer {
			/** @param TableTracer<string> $tracer */
			public function transform( string|array|DOMElement $element, int $position, object $tracer ): string {
				$content = $element instanceof DOMElement ? $element->textContent : $element[3];

				TestCase::assertIsString( $content );

				return str_contains( $content, 'Two' ) ? '' : $content;
			}
		};

		foreach ( array( new DOMNodeScanner(), new DOMStringScanner() ) as $scanner ) {
			$scanner->addTransformer( Table::Column, $tdMarshaller )->inferTableFrom( $source );

			$this->assertNotEmpty( $data = $scanner->getTableData()[ $scanner->getTableId()[0] ]->current()->getArrayCopy() );
			$this->assertSame( 2, $scanner->getCurrentIterationCountOf( Table::Column ) );
			$this->assertArrayHasKey( 'First', $data );
			$this->assertArrayNotHasKey( 'Last', $data, 'Skips falsy (empty string) transformed value.' );
		}
	}

	#[Test]
	public function itCascadesParentCurrentCountIfTdHasTable(): void {
		$source = '
			<table id="first-table">
				<caption> This is a <b>Caption</b> content </caption>
				<tbody id="first-body">
				<!-- comment -->
				<tr><th>Top 0</th><th><!-- comment -->Top 1</th><!-- comment --><!-- comment --><th>Top 2</th><!-- comment --><!-- comment --></tr>
				<!-- comment -->
				<tr>
				<!-- comment -->
				<!-- comment -->
					<td>0</td>
					<!-- comment -->
					<!-- comment -->
					<!-- comment -->
					<td>1
						<!-- comment -->
						<table id="middle-table"><!-- comment --><tbody id="middle-body">
							<tr><th><!-- comment --><!-- comment -->Middle 0</th><th><!-- comment -->Middle 1</th><th>Middle 2</th></tr>
							<tr>
								<!-- comment -->
								<td>zero:</td>
								<!-- comment -->
								<!-- Inner table -->
								<td>one:<!-- comment -->
									<!-- Final table -->
									<div>
										<table id="last-table"><tbody id="last-body">
											<tr><!-- comment --><!-- comment --><th>Last 0</th><th><!-- comment --><!-- comment -->Last 1</th></tr>
											<tr><td>O=</td><td>I=<!-- comment --></td><!-- comment --><!-- comment --></tr>
										</tbody></table>
									</div>
								</td>
								<td>two:</td>
							</tr>
						</tbody></table>
					</td>
					<td>2</td>
				</tr>
			</tbody></table>
		';

		$transformer = new /** @template-implements Transformer<DOMNodeScanner|DOMStringScanner,string> */ class()
		implements Transformer {
			public function __construct( private ?Closure $asserter = null ) {}

			public function transform( string|array|DOMElement $element, int $position, object $tracer ): mixed {
				// @phpstan-ignore-next-line -- Invocable & returns value based on $this->asserter.
				return ( $this->asserter )( $element, $position, $tracer );
			}
		};

		$captionAsserter = static function ( string|array|DOMElement $el, int $position, TableTracer $tracer ) {
			$data = array();

			// @phpstan-ignore-next-line -- Always a string if not DOMElement.
			$el   = $el instanceof DOMElement ? $el : DOMDocumentFactory::bodyFromHtml( $el, false )->firstChild;
			$list = $el?->childNodes;

			$data['text1'] = trim( $list?->item( 0 )->textContent ?? '' );
			$data['bold1'] = trim( $list?->item( 1 )->textContent ?? '' );
			$data['text2'] = trim( $list?->item( 2 )->textContent ?? '' );

			return json_encode( $data );
		};

		$thAsserter = static function ( string|array|DOMElement $el, int $position, TableTracer $tracer ) {
			if ( ! $el instanceof DOMElement ) {
				TestCase::assertIsString( $el );

				$text     = trim( strip_tags( $el ) );
				$expected = (int) substr( $text, -1 );

				match ( true ) {
					default => throw new LogicException( 'This should never be thrown. All <th>s are covered.' ),

					str_starts_with( $text, 'Top' ) => self::assertKeyAndPositionInTH( $tracer, $text, $expected, $position )
				};

				return $text;
			}

			$text     = trim( $el->textContent );
			$expected = (int) substr( $text, -1 );

			match ( true ) {
				default => throw new LogicException( 'This should never be thrown. All <th>s are covered.' ),

				str_starts_with( $text, 'Top' )    => self::assertKeyAndPositionInTH( $tracer, $text, $expected, $position ),
				str_starts_with( $text, 'Middle' ) => self::assertKeyAndPositionInTH( $tracer, $text, $expected, $position ),
				str_starts_with( $text, 'Last' )   => self::assertKeyAndPositionInTH( $tracer, $text, $expected, $position ),
			};

			return $text;
		};

		$trAsserter = static function ( string|array|DOMElement $el, int $pos, TableTracer $tracer ) use ( $source ) {
			self::assertNull(
				$tracer->getCurrentIterationCountOf( Table::Head ),
				'Head count is not accessible when inferring <tr> content.'
			);

			if ( ! $el instanceof DOMElement ) {
				self::assertStringContainsString(
					base64_decode( (string) $tracer->getTableId( true ) ), // phpcs:ignore
					Normalize::controlsAndWhitespacesIn( $source ),
					'64-bit hash is set as Table ID when using ' . HtmlTableFromString::class
				);

				// @phpstan-ignore-next-line -- Always array if not DOMElement.
				$result = $tracer->inferTableDataFrom( $el );

				self::assertSame(
					3,
					$tracer->getCurrentIterationCountOf( Table::Column ),
					'Only discovers <tr> upto nested table. No negative look-head support.'
				);

				return new ArrayObject( $result );
			}

			$body = $el->parentNode;

			assert( $body instanceof DOMElement );

			$table = $body->parentNode;

			assert( $table instanceof DOMElement );

			self::assertSame( spl_object_id( $table ) * spl_object_id( $body ), $tracer->getTableId( true ) );

			$result = $tracer->inferTableDataFrom( $el->childNodes );
			$id     = $table->getAttribute( 'id' );

			self::assertSame(
				'last-table' === $id ? 2 : 3,
				$tracer->getCurrentIterationCountOf( Table::Column ),
				'Column count is accessible when <td> is inferred within <tr> Transformer.'
			);

			return new ArrayObject( $result );
		};

		$tdAsserter = static function ( string|array|DOMElement $el, int $pos, TableTracer $tracer ) {
			self::assertNull(
				$tracer->getCurrentIterationCountOf( Table::Head ),
				'Head count is not accessible when inferring <td> content.'
			);

			if ( ! $el instanceof DOMElement ) {
				match ( true ) {
					default => throw new LogicException( 'This should never be thrown. All <td>s are covered.' ),

					'td' === $el[1] && 'td' === $el[4] // top level table.
						=> self::assertKeyAndPositionInTD( $tracer, 'Top 0', 0, $pos ),
					'td' === $el[1] && 'th' === $el[4] // switches to inner table.
						=> self::assertKeyAndPositionInTD( $tracer, 'Top 1', 1, $pos ),
					'th' === $el[1] && 'th' === $el[4] // continues with inner table.
						=> self::assertKeyAndPositionInTD( $tracer, 'Top 2', 2, $pos )

					// NOTE: second closing </tr> stops after this table head. No further tracing.
				};

				return $el;
			}

			$text = $el->textContent;

			match ( true ) {
				default => throw new LogicException( 'This should never be thrown. All <td>s are covered.' ),

				str_starts_with( $text, '0' )  => self::assertKeyAndPositionInTD( $tracer, 'Top 0', 0, $pos ),
				str_starts_with( $text, '1' )  => self::assertKeyAndPositionInTD( $tracer, 'Top 1', 1, $pos ),
				str_starts_with( $text, '2' )  => self::assertKeyAndPositionInTD( $tracer, 'Top 2', 2, $pos ),

				str_starts_with( $text, 'zero:' ) => self::assertKeyAndPositionInTD( $tracer, 'Middle 0', 0, $pos ),
				str_starts_with( $text, 'one:' )  => self::assertKeyAndPositionInTD( $tracer, 'Middle 1', 1, $pos ),
				str_starts_with( $text, 'two:' )  => self::assertKeyAndPositionInTD( $tracer, 'Middle 2', 2, $pos ),

				str_starts_with( $text, 'O=' ) => self::assertKeyAndPositionInTD( $tracer, 'Last 0', 0, $pos ),
				str_starts_with( $text, 'I=' ) => self::assertKeyAndPositionInTD( $tracer, 'Last 1', 1, $pos ),
			};

			return $text;
		};

		foreach ( array( new DOMNodeScanner(), new DOMStringScanner() ) as $scanner ) {
			$scanner->withAllTables()
				->addTransformer( Table::Caption, new $transformer( $captionAsserter ) )
				->addTransformer( Table::Head, new $transformer( $thAsserter ) )
				->addTransformer( Table::Row, new $transformer( $trAsserter ) )
				->addTransformer( Table::Column, new $transformer( $tdAsserter ) )
				->inferTableFrom( $source );

			$this->assertSame(
				array( 'text1' => 'This is a', 'bold1' => 'Caption', 'text2' => 'content' ),
				json_decode( $scanner->getTableCaption()[ $scanner->getTableId()[0] ] ?? '', associative: true )
			);
		}
	}

	/** @param TableTracer<string> $tracer */
	private static function assertKeyAndPositionInTH(
		TableTracer $tracer,
		string $headContent,
		int $expectedPosition,
		int $actualPosition
	): void {
		self::assertTrue( str_ends_with( $headContent, (string) $actualPosition ) );
		self::assertSame( $expectedPosition, $actualPosition );
		self::assertSame( $expectedPosition + 1, $tracer->getCurrentIterationCountOf( Table::Head ) );
	}

	/** @param TableTracer<string> $tracer */
	private static function assertKeyAndPositionInTD(
		TableTracer $tracer,
		string $key,
		int $expectedPosition,
		int $actualPosition
	): void {
		self::assertSame( $key, $tracer->getCurrentColumnName() );
		self::assertSame( $expectedPosition, $actualPosition );
		self::assertSame( $expectedPosition + 1, $tracer->getCurrentIterationCountOf( Table::Column ) );
	}

	#[Test]
	public function itIgnoresTracedStructureOfNoBodyFound(): void {
		$source = '<table><caption>This is caption</caption><thead><tr><th>This is head</th></tr></thead></table>';

		$scanner = new DOMNodeScanner();

		$scanner->inferTableFrom( $source );

		$this->assertEmpty( $scanner->getTableId() );
	}
}


// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
/** @template-implements TableTracer<string> */
class DOMNodeScanner implements TableTracer {
	/** @use HtmlTableFromNode<string> */
	use HtmlTableFromNode;

	/** @param Closure(DOMElement, self): bool $validator */
	public function __construct( private ?Closure $validator = null ) {}

	protected function isTargetedTable( DOMElement $node ): bool {
		return $this->validator ? ( $this->validator )( $node, $this ) : true;
	}
}

/** @template-implements TableTracer<string> */
class DOMStringScanner implements TableTracer {
	/** @use HtmlTableFromString<string> */
	use HtmlTableFromString;

	/** @param Closure(string, self): bool $validator */
	public function __construct( private ?Closure $validator = null ) {}

	protected function isTargetedTable( string $node ): bool {
		return $this->validator ? ( $this->validator )( $node, $this ) : true;
	}
}
