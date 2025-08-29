<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Table;

use Closure;
use DOMNode;
use Exception;
use DOMElement;
use ArrayObject;
use DOMDocument;
use LogicException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Enums\EventAt;
use TheWebSolver\Codegarage\Test\Fixture\StripTags;
use TheWebSolver\Codegarage\Scraper\Helper\Normalize;
use TheWebSolver\Codegarage\Scraper\Event\TableTraced;
use TheWebSolver\Codegarage\Scraper\DOMDocumentFactory;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Test\DOMDocumentFactoryTest;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Attributes\CollectUsing;
use TheWebSolver\Codegarage\Scraper\Marshaller\MarshallItem;
use TheWebSolver\Codegarage\Test\Fixture\Table\NodeTableTracer;
use TheWebSolver\Codegarage\Scraper\Marshaller\MarshallTableRow;
use TheWebSolver\Codegarage\Test\Fixture\Table\StringTableTracer;
use TheWebSolver\Codegarage\Scraper\Traits\Table\HtmlTableFromString;

class TableExtractorTest extends TestCase {
	private function getTableContent( string $filename = 'table' ): string {
		return file_get_contents( DOMDocumentFactoryTest::RESOURCE_PATH . DIRECTORY_SEPARATOR . "{$filename}.html" ) ?: '';
	}

	/** @param class-string<TableTracer<string>> $classname */
	#[Test]
	#[DataProvider( 'provideInvalidSource' )]
	public function itThrowsExceptionWhenInvalidSourceGiven(
		string $classname,
		string|DOMElement $element,
		?int $count = null
	): void {
		if ( null === $count ) {
			$this->expectException( InvalidSource::class );
		}

		$tracer = new $classname();

		$tracer->inferTableFrom( $element, false );

		// @phpstan-ignore-next-line -- "null" always throws exception.
		$this->assertCount( $count, $tracer->getTableId() );
	}

	/** @return mixed[] */
	public static function provideInvalidSource(): array {
		$divElement  = new DOMElement( 'div' );
		$string      = '<div></div>';
		$tableString = '<table><tbody><tr><td>content</td></tr></tbody></table>';

		$dom = new DOMDocument();
		$dom->loadHTML( $tableString, LIBXML_NOERROR | LIBXML_NOBLANKS );

		return [
			[ NodeTableTracer::class, $divElement ],
			[ NodeTableTracer::class, $string, 0 ], // Table from Node Does not verify string value.
			[ NodeTableTracer::class, $dom->getElementsByTagName( 'body' )->item( 0 )?->firstChild, 1 ],
			[ StringTableTracer::class, $divElement ],
			[ StringTableTracer::class, $string ],
			[ StringTableTracer::class, $tableString, 1 ],
		];
	}

	#[Test]
	#[DataProvider( 'provideTableStructuresForTracing' )]
	public function itTracesTableStructureOnlyWhenBodyExists( string $source, bool $hasHead, int $idsCount = 0 ): void {
		foreach ( [ new StringTableTracer(), new NodeTableTracer() ] as $tracer ) {
			$tracer->inferTableFrom( $source );

			$heads   = $tracer->getTableHead();
			$columns = $tracer->getTableData();

			if ( $idsCount ) {
				$this->assertNotEmpty( $columns );
			} else {
				$this->assertEmpty( $columns );
			}

			if ( $hasHead ) {
				$this->assertNotEmpty( $heads, $tracer::class );
			} else {
				$this->assertEmpty( $heads, $tracer::class );
			}
		}
	}

	/** @return mixed[] */
	public static function provideTableStructuresForTracing(): array {
		return [
			[ '<table><thead><!-- only heads --><tr><th>head</th></tr></thead></table>', false ],
			[ '<table><tbody><!-- only heads --><tr><th>head</th></tr></tbody></table>', false, 0 ],
			[ '<table><tbody><tr><th>head</th></tr><tr><td>col</td></tr></tbody></table>', true, 1 ],
			[ '<table><thead><tr><th>head</th></tr></thead><tbody><tr><td>col</td></tr></tbody></table>', true, 1 ],
			[ '<table><tbody><!-- empty rows --><tr></tr><tr></tr></tbody></table>', false ],
			[ '<table><tbody><!-- no rows --></tbody></table>', false ],
			[ '<table><tbody id="no-childNodes"></tbody></table>', false ],
			[ '<table></table>', false ],
		];
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
						<!-- There is not space, only tab in below title -->
						<th>  Another					Title
						</th>
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

		foreach ( [ new StringTableTracer(), new NodeTableTracer() ] as $tracer ) {
			$tracer->inferTableFrom( $source );

			$tableIds = $tracer->getTableId();
			$heads    = $tracer->getTableHead()[ $tableIds[0] ];
			$columns  = $tracer->getTableData()[ $tableIds[0] ];

			$this->assertCount( 1, $tableIds );
			$this->assertSame(
				[ 'Title', 'AnotherTitle' ],
				$heads->toArray(),
				'Source has no space character between "Another" and "Title" words. Only tabs.'
			);

			foreach ( $columns as $index => $dataset ) {
				$value = 0 === $index ? [ 'Heading 1', 'Value One' ] : [ 'Heading 2', 'Value Two' ];

				$this->assertSame( $value, $dataset->getArrayCopy() );
			}

			$this->assertSame( 2, (int) ( $index ?? 0 ) + 1 );

			$tracer->resetTableTraced();

			$this->assertCount( 1, $tracer->getTableId(), 'Table ID will not be reset' );
			$this->assertEmpty( $tracer->getTableData() );
		}//end foreach

		foreach ( [ new StringTableTracer(), new NodeTableTracer() ] as $tracer ) {
			$tracer->traceWithout( Table::THead )->inferTableFrom( $source );

			$this->assertEmpty( $tracer->getTableHead() );

			$columns = $tracer->getTableData()[ $tracer->getTableId( true ) ];

			// TODO: maybe use ::flush() and check if head is used as column names when traceWithout is used.
			$this->assertIsList( $columns->current()->getArrayCopy() );
		}
	}

	#[Test]
	public function itDoesNotCollectValueIfTransformerReturnsFalsyValue(): void {
		$source = '
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

		$validColumn = new /** @template-implements Transformer<TableTracer,string> */ class() implements Transformer {
			/** @param TableTracer<string> $tracer */
			public function transform( string|array|DOMElement $element, object $tracer ): string {
				$content = $element instanceof DOMElement ? $element->textContent : $element[3];

				TestCase::assertIsString( $content );

				return str_contains( $content, 'Two' ) ? '' : $content;
			}
		};

		foreach ( [ new StringTableTracer(), new NodeTableTracer() ] as $tracer ) {
			$tracer->addTransformer( Table::Column, $validColumn )->inferTableFrom( $source );

			$dataset = $tracer->getTableData()[ $tracer->getTableId()[0] ]->current()->getArrayCopy();

			$this->assertCount( 1, $dataset );
			$this->assertSame( 2, $tracer->getCurrentIterationCount( Table::Column ) );
			$this->assertSame( 'Value One', reset( $dataset ), 'Skips falsy (empty string) transformed value.' );
		}
	}

	#[Test]
	public function itOnlyTracesTableThatIsValidatedAsATargetedTable(): void {
		$nodeTracer = new class() extends NodeTableTracer {
			protected function isTargetedTable( string|DOMElement $node ): bool {
				return $node instanceof DOMElement && $node->getAttribute( 'id' ) === 'inner-content-table';
			}
		};

		$nodeTracer->inferTableFrom( $source = $this->getTableContent() );

		$this->assertCount( 1, $tableIds = $nodeTracer->getTableId() );
		$this->assertSame(
			[ '1: First Data', '2: Second Data' ],
			$nodeTracer->getTableData()[ $tableIds[0] ]->current()->getArrayCopy()
		);

		$stringTracer = new class() extends StringTableTracer {
			protected function isTargetedTable( string|DOMElement $node ): bool {
				return is_string( $node )
					&& str_contains( explode( '<div id="inner-content"', $node, 2 )[0], 'inner-content-table' );
			}
		};

		$stringTracer->addEventListener(
			Table::Row,
			static fn ( TableTraced $e ) => $e->tracer->setIndicesSource(
				// @phpstan-ignore-next-line
				CollectUsing::listOf( $e->tracer->getTableHead()[ $e->tracer->getTableId( true ) ]->toArray() )
			)
		)->inferTableFrom( $source );

		$tableIds = $stringTracer->getTableId();

		$this->assertCount( 1, $tableIds, 'Target validation does not apply. First table found.' );
		$this->assertSame( 'John Doe', $stringTracer->getTableData()[ $tableIds[0] ]->current()['Name'] );

		// Cannot target multiple table with string tracer.
		$this->expectException( ScraperError::class );
		$stringTracer->withAllTables( true );
	}

	/** NOTE: Conflicts with column count as well as with spanned row support. */
	public function itOnlyTracesTableColumnsThatIsValidatedAsATableColumnStructure(): void {
		$stringTracer = new class() extends NodeTableTracer {
			protected function isTableColumnStructure( mixed $node ): bool {
				return parent::isTableColumnStructure( $node )
					&& $node instanceof DOMNode
					&& ! str_ends_with( $node->firstChild->textContent ?? '', 'per' );
			}
		};

		$nodeTracer = new class() extends StringTableTracer {
			protected function isTableColumnStructure( mixed $node ): bool {
				return parent::isTableColumnStructure( $node )
					&& is_array( $node ) && is_string( $node[3] ?? null )
					&& ! str_ends_with( $node[3], 'per' );
			}
		};

		foreach ( [ $stringTracer, $nodeTracer ] as $tracer ) {
			$tracer->inferTableFrom( $this->getTableContent( 'single-table' ) );

			$id = $tracer->getTableId( true );

			$this->assertCount( 3, $tracer->getTableData()[ $id ]->current()->getArrayCopy() );
		}
	}

	/**
	 * @param mixed[]             $args
	 * @param TableTracer<string> $tracer
	 */
	#[Test]
	#[DataProvider( 'provideCasesWhenEventListenerExceptionIsThrown' )]
	public function itThrowsExceptionWhenMethodsToBeInvokedInsideEventListenerIsInvokedEarly(
		string $methodName,
		array $args,
		TableTracer $tracer,
		?string $throwing = null,
	): void {
		$method = $tracer::class . '::' . ( $throwing ?? $methodName );
		$values = [ $method, Table::class . '::' . Table::Row->name, EventAt::class . '::' . EventAt::Start->name, '' ];

		$this->expectException( ScraperError::class );
		$this->expectExceptionMessage( sprintf( TableTracer::USE_EVENT_LISTENER, ...$values ) );

		$tracer->{$methodName}( ...$args );
	}

	/** @return mixed[] */
	public static function provideCasesWhenEventListenerExceptionIsThrown(): array {
		$collection = CollectUsing::listOf( [ 'exception', 'thrown', 'test' ] );
		$listener   = static fn( TableTraced $e ) => $e->tracer->setIndicesSource( $collection );
		$table      = DOMDocumentFactoryTest::RESOURCE_PATH . DIRECTORY_SEPARATOR . 'table.html';

		return [
			[ 'setIndicesSource', [ $collection ], new NodeTableTracer() ],
			[ 'setIndicesSource', [ $collection ], new StringTableTracer() ],
			[
				'inferTableFrom',
				[ $table ],
				( new NodeTableTracer() )->addEventListener( Table::TBody, $listener ),
				'setIndicesSource',
			],
			[
				'inferTableFrom',
				[ $table ],
				( new NodeTableTracer() )->addEventListener( Table::THead, $listener, EventAt::End ),
				'setIndicesSource',
			],
		];
	}

	#[Test]
	public function itGetsTheTargetedTableNode(): void {
		$stripTags            = new StripTags();
		$firstTableColumNames = [ 'Name', 'Title', 'Address' ];
		$listener             = static function ( TableTraced $e ) {
			$id    = $e->tracer->getTableId( true );
			$heads = $e->tracer->getTableHead()[ $id ] ?? false; // Not all tables in table.html have head.

			// @phpstan-ignore-next-line
			$heads && $e->tracer->setIndicesSource( CollectUsing::listOf( $heads->toArray() ) );
		};

		foreach ( [ new StringTableTracer(), $domTracer = new NodeTableTracer() ] as $tracer ) {
			$tracer
				->addTransformer( Table::Head, $stripTags )
				->addTransformer( Table::Column, $stripTags )
				->addEventListener( Table::Row, $listener )
				->withAllTables()
				->inferTableFrom( $this->getTableContent() );

			$ids = $tracer->getTableId();

			$this->assertCount( $tracer instanceof NodeTableTracer ? 2 : 1, $ids );

			$heads    = $tracer->getTableHead()[ $ids[0] ]->toArray();
			$columns  = $tracer->getTableData();
			$devTable = $columns[ $ids[0] ];

			$this->assertSame( $firstTableColumNames, $heads );
			$this->assertEqualsCanonicalizing( array_keys( $first = $devTable->current()->getArrayCopy() ), $heads );
			$this->assertSame(
				[ 'John Doe', 'PHP Developer', 'Ktm' ],
				array_values( $first ),
				$tracer::class . ' -> First row dataset of first table matches'
			);

			$tracer->addEventListener(
				Table::Row,
				static fn( TableTraced $e ) => $e->tracer->setIndicesSource( CollectUsing::listOf( [ 'finalAddress' ] ) )
			);

			$devTable->next();

			$this->assertCount(
				$tracer instanceof NodeTableTracer ? 3 : 1,
				$tracer->getTableId(),
				$tracer::class . ' -> Nested table inside third <tr> of first table is discoverable by Node Tracer.'
			);
			$this->assertSame(
				$tracer instanceof NodeTableTracer ? [ 'finalAddress' ] : $firstTableColumNames,
				$tracer->getIndicesSource()?->items,
				$tracer::class . ' -> Column name of nested table inside third <tr> of first table has one column discoverable by Node Tracer'
			);

			$this->assertSame(
				[ 'Lorem Ipsum', 'JS Developer', 'Bkt' ],
				array_values( $devTable->current()->getArrayCopy() ),
				'Mapped values so String Tracer values tags are stripped'
			);
		}//end foreach

		// These can only be discovered by Node Tracer.
		$domTableIds = $domTracer->getTableId();
		$dataTable   = $domTracer->getTableData()[ $domTableIds[1] ];

		$this->assertSame(
			[ '1: First Data', '2: Second Data' ],
			array_values( $dataTable->current()->getArrayCopy() )
		);

		$addressTableId = $domTracer->getTableId( true );

		$this->assertSame( $addressTableId, end( $domTableIds ) );
		$this->assertSame(
			'Bkt',
			$domTracer->getTableData()[ $addressTableId ]->current()->getArrayCopy()['finalAddress']
		);
	}

	#[Test]
	public function itValidatesTransformerElementAndScope(): void {
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

		$transformer = new /** @template-implements Transformer<TableTracer<string>,string> */ class() implements Transformer {
			public function __construct( private ?Closure $asserter = null ) {}

			public function transform( string|array|DOMElement $element, object $tracer ): mixed {
				// @phpstan-ignore-next-line -- Invocable & returns value based on $this->asserter.
				return ( $this->asserter )( $element, $tracer );
			}
		};

		$captionAsserter = static function ( string|array|DOMElement $el ) {
			// @phpstan-ignore-next-line -- Always an array if not DOMElement.
			$el   = $el instanceof DOMElement ? $el : DOMDocumentFactory::bodyFromHtml( $el[0], false )->firstChild;
			$list = $el?->childNodes;
			$data = [];

			$data['text1'] = trim( $list?->item( 0 )->textContent ?? '' );
			$data['bold1'] = trim( $list?->item( 1 )->textContent ?? '' );
			$data['text2'] = trim( $list?->item( 2 )->textContent ?? '' );

			return json_encode( $data );
		};

		$thAsserter = static function ( string|array|DOMElement $el, TableTracer $tracer ) {
			if ( ! $el instanceof DOMElement ) {
				// @phpstan-ignore-next-line -- Always an array if not DOMElement.
				$text     = trim( strip_tags( $el[3] ) );
				$expected = (int) substr( $text, -1 );

				match ( true ) {
					default => throw new LogicException( 'This should never be thrown. All <th>s are covered.' ),

					str_starts_with( $text, 'Top' ) => self::assertKeyAndPositionInTH( $tracer, $text, $expected )
				};

				return $text;
			}

			$text     = trim( $el->textContent );
			$expected = (int) substr( $text, -1 );

			match ( true ) {
				default => throw new LogicException( 'This should never be thrown. All <th>s are covered.' ),

				str_starts_with( $text, 'Top' )    => self::assertKeyAndPositionInTH( $tracer, $text, $expected ),
				str_starts_with( $text, 'Middle' ) => self::assertKeyAndPositionInTH( $tracer, $text, $expected ),
				str_starts_with( $text, 'Last' )   => self::assertKeyAndPositionInTH( $tracer, $text, $expected ),
			};

			return $text;
		};

		$trAsserter = static function ( string|array|DOMElement $el, TableTracer $tracer ) use ( $source ) {
			self::assertNull(
				$tracer->getCurrentIterationCount( Table::Head ),
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
					$tracer->getCurrentIterationCount( Table::Column ),
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
				$tracer->getCurrentIterationCount( Table::Column ),
				'Column count is accessible when <td> is inferred within <tr> Transformer.'
			);

			return new ArrayObject( $result );
		};

		$tdAsserter = static function ( string|array|DOMElement $el, TableTracer $tracer ) {
			self::assertNull(
				$tracer->getCurrentIterationCount( Table::Head ),
				'Head count is not accessible when inferring <td> content.'
			);

			if ( ! $el instanceof DOMElement ) {
				match ( true ) {
					default => throw new LogicException( 'This should never be thrown. All <td>s are covered.' ),

					'td' === $el[1] && 'td' === $el[4] // top level table.
						=> self::assertKeyAndPositionInTD( $tracer, 'Top 0', 0 ),
					'td' === $el[1] && 'th' === $el[4] // switches to inner table.
						=> self::assertKeyAndPositionInTD( $tracer, 'Top 1', 1 ),
					'th' === $el[1] && 'th' === $el[4] // continues with inner table.
						=> self::assertKeyAndPositionInTD( $tracer, 'Top 2', 2 )

					// NOTE: second closing </tr> stops after this table head. No further tracing.
				};

				return $el;
			}

			$text = $el->textContent;

			match ( true ) {
				default => throw new LogicException( 'This should never be thrown. All <td>s are covered.' ),

				str_starts_with( $text, '0' )  => self::assertKeyAndPositionInTD( $tracer, 'Top 0', 0 ),
				str_starts_with( $text, '1' )  => self::assertKeyAndPositionInTD( $tracer, 'Top 1', 1 ),
				str_starts_with( $text, '2' )  => self::assertKeyAndPositionInTD( $tracer, 'Top 2', 2 ),

				str_starts_with( $text, 'zero:' ) => self::assertKeyAndPositionInTD( $tracer, 'Middle 0', 0 ),
				str_starts_with( $text, 'one:' )  => self::assertKeyAndPositionInTD( $tracer, 'Middle 1', 1 ),
				str_starts_with( $text, 'two:' )  => self::assertKeyAndPositionInTD( $tracer, 'Middle 2', 2 ),

				str_starts_with( $text, 'O=' ) => self::assertKeyAndPositionInTD( $tracer, 'Last 0', 0 ),
				str_starts_with( $text, 'I=' ) => self::assertKeyAndPositionInTD( $tracer, 'Last 1', 1 ),
			};

			return $text;
		};

		$listener = static fn( TableTraced $e ) => $e->tracer->setIndicesSource(
			// @phpstan-ignore-next-line
			CollectUsing::listOf( $e->tracer->getTableHead()[ $e->tracer->getTableId( true ) ]->toArray() )
		);

		foreach ( [ new StringTableTracer(), new NodeTableTracer() ] as $tracer ) {
			$tracer->withAllTables()
				->addTransformer( Table::Caption, new $transformer( $captionAsserter ) )
				->addTransformer( Table::Head, new $transformer( $thAsserter ) )
				->addTransformer( Table::Row, new $transformer( $trAsserter ) )
				->addTransformer( Table::Column, new $transformer( $tdAsserter ) )
				->addEventListener( Table::Row, $listener )
				->inferTableFrom( $source );

			$this->assertSame(
				// phpcs:disable WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
				[ 'text1' => 'This is a', 'bold1' => 'Caption', 'text2' => 'content' ],
				json_decode( $tracer->getTableCaption()[ $tracer->getTableId()[0] ] ?? '', associative: true )
			);
		}
	}

	/** @param TableTracer<string> $tracer */
	private static function assertKeyAndPositionInTH(
		TableTracer $tracer,
		string $headContent,
		int $expectedPosition,
	): void {
		self::assertTrue( str_ends_with( $headContent, (string) $expectedPosition ) );
		self::assertSame( $expectedPosition + 1, $tracer->getCurrentIterationCount( Table::Head ) );
	}

	/** @param TableTracer<string> $tracer */
	private static function assertKeyAndPositionInTD(
		TableTracer $tracer,
		string $key,
		int $expectedPosition,
	): void {
		self::assertSame( $key, $tracer->getCurrentItemIndex() );
		self::assertSame( $expectedPosition + 1, $tracer->getCurrentIterationCount( Table::Column ) );
	}

	#[Test]
	public function itInsertsSpannedRowsToRespectiveColumnPosition(): void {
		$indexKeys   = [ 'Card Name', 'ID Range', 'Needs Validation', 'Length', 'Validator' ];
		$transformer = new class() extends MarshallItem {
			public function transform( string|array|DOMElement $element, object $scope ): string {
				$value   = parent::transform( $element, $scope );
				$content = is_string( $element ) ? $element : ( is_array( $element ) ? $element[3] : $element->textContent );

				[$count, $columnData] = match ( $content ) {
					// @phpstan-ignore-next-line -- Exception must never be thrown as all case must match.
					default => throw new Exception( "Unmatched element content with spanned row: $content" ),

					'American Express', 'Bankcard', 'Diners Club', 'Diners Club International', 'Discover Card' => [ 1, $content ],
					'34, 37', '5610, 560221-560225', '', '30, 36, 38, 39', '6011, 644-649, 65', '622126-622925' => [ 2, $content ],
					'Yes', 'No'                                                                                 => [ 3, $content ],
					'15', '16', '14-19', '16-19'                                                                => [ 4, $content ],
					'Luhn algorithm', 'No Validation'                                                           => [ 5, $content ]
				};

				// @phpstan-ignore-next-line
				TestCase::assertSame( $count, $scope->getCurrentIterationCount( Table::Column ), $columnData );

				return $value ? $value : 'N/A';
			}
		};

		foreach ( [ new NodeTableTracer(), new StringTableTracer() ] as $tracer ) {
			$tracer
				->addEventListener( Table::Row, static fn( $e ) => $e->tracer->setIndicesSource( CollectUsing::listOf( $indexKeys ) ) )
				->addTransformer( Table::Column, $transformer )
				->addTransformer( Table::Row, new MarshallTableRow( 'Fails if could not verify count [%1$s] "%2$s"' ) ) // @phpstan-ignore-line
				->inferTableFrom( file_get_contents( DOMDocumentFactoryTest::RESOURCE_PATH . '/table-spanned.html' ) ?: '' );

			$iterator = $tracer->getTableData()[ $tracer->getTableId( true ) ];

			$americanExpress = $iterator->current()->getArrayCopy();

			$this->assertSame( $indexKeys, array_keys( $americanExpress ) );
			$this->assertSame(
				[ 'American Express', '34, 37', 'Yes', '15', 'Luhn algorithm' ],
				array_values( $americanExpress )
			);

			$iterator->next();
			$this->assertSame(
				[ 'Bankcard', '5610, 560221-560225', 'No', '16', 'Luhn algorithm' ],
				array_values( $iterator->current()->getArrayCopy() )
			);

			$iterator->next();
			$this->assertSame(
				[ 'Diners Club', 'N/A', 'Yes', '15', 'No Validation' ],
				array_values( $iterator->current()->getArrayCopy() )
			);

			$iterator->next();
			$this->assertSame(
				[ 'Diners Club International', '30, 36, 38, 39', 'Yes', '14-19', 'Luhn algorithm' ],
				array_values( $iterator->current()->getArrayCopy() )
			);

			$iterator->next();
			$this->assertSame(
				[ 'Discover Card', '6011, 644-649, 65', 'Yes', '16-19', 'Luhn algorithm' ],
				array_values( $iterator->current()->getArrayCopy() )
			);

			$iterator->next();
			$this->assertSame(
				[ 'Discover Card', '622126-622925', 'Yes', '16-19', 'Luhn algorithm' ],
				array_values( $iterator->current()->getArrayCopy() )
			);
		}//end foreach
	}
}
