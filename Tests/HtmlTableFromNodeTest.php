<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use Closure;
use DOMNode;
use Exception;
use DOMElement;
use ArrayObject;
use DOMNodeList;
use LogicException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\AssertDOMElement;
use TheWebSolver\Codegarage\Scraper\DOMDocumentFactory;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Traits\HtmlTableFromNode;
use TheWebSolver\Codegarage\Scraper\Marshaller\TableRowMarshaller;

class HtmlTableFromNodeTest extends TestCase {
	final public const TABLE_SOURCE = __DIR__ . DIRECTORY_SEPARATOR . 'Resource' . DIRECTORY_SEPARATOR . 'table.html';

	#[Test]
	public function itOnlyScansTargetedTable(): void {
		$dom        = DOMDocumentFactory::createFromHtml( self::TABLE_SOURCE );
		$innerTable = static function ( DOMElement $node ) {
			return AssertDOMElement::hasId( $node, id: 'inner-content-table' );
		};

		$scanner = new DOMNodeScanner( $innerTable );

		$scanner->inferTableFrom( $dom->childNodes );

		$this->assertCount( 1, $tableIds = $scanner->getTableId() );
		$this->assertCount( 2, $scanner->getTableData()[ $tableIds[0] ]->current() );
	}

	#[Test]
	public function itOnlyScansTargetedTableColumn(): void {
		$dom = DOMDocumentFactory::createFromHtml( self::TABLE_SOURCE );
		$dev = static function ( DOMElement $node ) {
			return AssertDOMElement::hasId( $node, id: 'developer-list' );
		};

		$scanner = new DOMNodeScanner( $dev );
		$scanner->subscribeWith(
			fn( $scanner ) => $scanner->setColumnNames( array( 'name', 'title' ), $scanner->getTableId( true ) ),
			Table::TBody
		);

		$scanner->inferTableFrom( $dom->childNodes );

		$this->assertSame(
			array(
				'name'  => 'John Doe',
				'title' => 'PHP Developer',
			),
			$scanner->getTableData()[ $scanner->getTableId( true ) ]->current()->getArrayCopy()
		);

		$scanner = new class() extends DOMNodeScanner {
			protected function isTableColumnStructure( mixed $node ): bool {
				return parent::isTableColumnStructure( $node )
					&& ! str_ends_with( $node->firstChild->textContent ?? '', 'Developer' );
			}
		};

		$scanner->subscribeWith(
			fn( $scanner ) => $scanner->setColumnNames( array( 'name', 'address' ), $scanner->getTableId( true ) ),
			Table::TBody
		);

		$scanner->inferTableFrom( $dom->childNodes );

		$this->assertSame(
			array(
				'name'    => 'John Doe',
				'address' => 'Ktm',
			),
			$scanner->getTableData()[ $scanner->getTableId( true ) ]->current()->getArrayCopy()
		);
	}

	/**
	 * @param list<string> $columnNames
	 * @param list<int>    $offset
	 * @param mixed[]      $expected
	 */
	#[Test]
	#[DataProvider( 'provideTableColumnDataWithOffset' )]
	public function itOffsetsInBetweenIndicesOfColumnNames( array $columnNames, array $offset, array $expected ): void {
		$table   = '<table><tbody><tr><td>0</td><td>1</td><td>2</td><td>3</td><td>4</td></tr></tbody</table>';
		$dom     = DOMDocumentFactory::createFromHtml( $table );
		$scanner = new DOMNodeScanner();
		$scanner->subscribeWith(
			fn( $scanner ) => $scanner->setColumnNames( $columnNames, $scanner->getTableId( true ), ...$offset ),
			Table::TBody
		);

		/** @var TableRowMarshaller<string> */
		$tr = new TableRowMarshaller( 'Should Not Throw exception' );

		$scanner->transformWith( $tr, Table::Row )->inferTableFrom( $dom->childNodes );

		$this->assertSame(
			$expected,
			$scanner->getTableData()[ $scanner->getTableId( true ) ]->current()->getArrayCopy()
		);
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

	/** @param mixed[] $args */
	#[Test]
	#[DataProvider( 'provideMethodsThatThrowsException' )]
	public function itThrowsExceptionWhenInvokedBeforeTableFound( string $methodName, array $args ): void {
		$scanner = new DOMNodeScanner();
		$class   = DOMNodeScanner::class;

		$this->expectException( ScraperError::class );
		$this->expectExceptionMessage( sprintf( DOMNodeScanner::USE_EVENT_DISPATCHER, $class, $methodName, '' ) );

		$scanner->{$methodName}( ...$args );
	}

	/** @return mixed[] */
	public static function provideMethodsThatThrowsException(): array {
		return array(
			array( 'setColumnNames', array( array(), 0 ) ),
		);
	}

	#[Test]
	public function itGetsTheTargetedTableNode(): void {
		$handler      = new DOMNodeScanner();
		$dom          = DOMDocumentFactory::createFromHtml( self::TABLE_SOURCE );
		$thMarshaller = new class() implements Transformer {
			/** @param TableTracer<mixed,string> $tracer */
			public function transform( string|DOMElement $element, int $position, TableTracer $tracer ): string {
				return explode( '[', $element instanceof DOMElement ? $element->textContent : $element )[0];
			}
		};

		$handler
			->transformWith( $thMarshaller, Table::Head )
			->withAllTables()
			->inferTableFrom( $dom->childNodes );

		$ids  = $handler->getTableId();
		$th   = $handler->getTableHead( true )[ $ids[0] ]->toArray();
		$data = $handler->getTableData();

		$this->assertCount( 2, $handler->getTableId() );

		$devTable  = $data[ $ids[0] ];
		$dataTable = $data[ $ids[1] ];

		$this->assertSame( array( 'Name', 'Title', 'Address' ), $th );
		$this->assertEqualsCanonicalizing( array_keys( $first = $devTable->current()->getArrayCopy() ), $th );
		$this->assertSame( array( 'John Doe', 'PHP Developer', 'Ktm' ), array_values( $first ) );

		$handler->subscribeWith(
			static fn( $i ) => $i->setColumnNames( array( 'finalAddress' ), $i->getTableId( true ) ),
			Table::TBody
		);

		$devTable->next();

		$this->assertCount( 3, $handler->getTableId(), 'Third table inside second <tr> element of first table.' );
		$this->assertSame( array( 'finalAddress' ), $handler->getColumnNames() );

		$this->assertSame(
			array( 'Lorem Ipsum', 'JS Developer', 'Bkt' ),
			array_values( $devTable->current()->getArrayCopy() )
		);

		$this->assertSame(
			array( '1: First Data', '2: Second Data' ),
			array_values( $dataTable->current()->getArrayCopy() )
		);

		$addressTableId = $handler->getTableId( true );

		$this->assertSame(
			'Bkt',
			$handler->getTableData()[ $addressTableId ]->current()->getArrayCopy()['finalAddress']
		);
	}

	#[Test]
	public function itScrapesDataFromTableHeadAndBodyElement(): void {
		$table = '
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

		$nodeList = DOMDocumentFactory::createFromHtml( $table )->childNodes;
		$scanner  = new DOMNodeScanner();

		$scanner->inferTableFrom( $nodeList );

		$tableIds  = $scanner->getTableId();
		$fixedHead = $scanner->getTableHead( namesOnly: true )[ $tableIds[0] ];
		$data      = $scanner->getTableData()[ $tableIds[0] ];

		$this->assertCount( 1, $tableIds );
		$this->assertSame( $headers = array( 'Title', 'Another Title' ), $fixedHead->toArray() );

		foreach ( $data as $index => $tableData ) {
			$value = 0 === $index ? array( 'Heading 1', 'Value One' ) : array( 'Heading 2', 'Value Two' );

			$this->assertSame( $headers, array_keys( $arrayCopy = $tableData->getArrayCopy() ) );
			$this->assertSame( $value, array_values( $arrayCopy ) );
		}

		$this->assertSame( 2, (int) ( $index ?? 0 ) + 1 );

		$scanner = new DOMNodeScanner();

		$scanner->traceWithout( Table::THead )->inferTableFrom( $nodeList );

		$this->assertEmpty( $scanner->getTableHead() );

		$data = $scanner->getTableData()[ $scanner->getTableId( true ) ];

		$this->assertIsList( $data->current()->getArrayCopy() );
	}

	#[Test]
	#[DataProvider( 'provideInvalidHtmlTable' )]
	public function itParsesInvalidTableGracefully( string $html, bool $hasHead = false ): void {
		$scanner = new DOMNodeScanner();

		$scanner->inferTableFrom( DOMDocumentFactory::createFromHtml( $html )->childNodes );

		$this->assertEmpty( $scanner->getTableData() );

		if ( $hasHead ) {
			$this->assertNotEmpty( $scanner->getTableHead() );
		}
	}

	/** @return mixed[] */
	public static function provideInvalidHtmlTable(): array {
		return array(
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
		$table   = '
			<table>
				<thead><tr><th>First</th><th>Last</th><tr></thead>
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

		$tdMarshaller = new class() implements Transformer {
			/** @param TableTracer<mixed,string> $tracer */
			public function transform( string|DOMElement $element, int $position, TableTracer $tracer ): string {
				$content = $element instanceof DOMElement ? $element->textContent : $element;

				return str_contains( $content, 'Two' ) ? '' : $content;
			}
		};

		$scanner->transformWith( $tdMarshaller, Table::Column )
			->inferTableFrom( DOMDocumentFactory::createFromHtml( $table )->childNodes );

		$this->assertNotEmpty( $data = $scanner->getTableData()[ $scanner->getTableId()[0] ]->current()->getArrayCopy() );
		$this->assertSame( 2, $scanner->getCurrentIterationCountOf( Table::Column ) );
		$this->assertArrayHasKey( 'First', $data );
		$this->assertArrayNotHasKey( 'Last', $data, 'Skips falsy (empty string) transformed value.' );
	}

	#[Test]
	public function itCascadesParentCurrentCountIfTdHasTable(): void {
		$table = '
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

		$dom         = DOMDocumentFactory::createFromHtml( $table );
		$scanner     = new DOMNodeScanner();
		$transformer = new class() implements Transformer {
			/** @param ?Closure(string|DOMElement,int,TableTracer<mixed,string>): (string|ArrayOBject<array-key,string[]>) $asserter */
			public function __construct( private ?Closure $asserter = null ) {}

			/**
			 * @param TableTracer<mixed,string> $tracer
			 * @return string|ArrayObject<array-key,string[]>
			 * @throws Exception When test performed without asserter.
			 */
			public function transform( string|DOMElement $element, int $position, TableTracer $tracer ): ArrayObject|string {
				! $this->asserter && throw new Exception( 'Asserter needed to test transformer.' );

				return ( $this->asserter )( $element, $position, $tracer );
			}
		};

		$captionAsserter = static function ( string|DOMElement $el, int $position, TableTracer $tracer ) {
			assert( $el instanceof DOMElement );

			$data = array();
			$list = $el->childNodes;

			$data['text1'] = trim( $list->item( 0 )->textContent ?? '' );
			$data['bold1'] = trim( $list->item( 1 )->textContent ?? '' );
			$data['text2'] = trim( $list->item( 2 )->textContent ?? '' );

			return json_encode( $data );
		};

		$thAsserter = static function ( string|DOMElement $el, int $position, TableTracer $tracer ) {
			self::assertInstanceOf( DOMElement::class, $el );

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

		$trAsserter = static function ( string|DOMElement $el, int $pos, TableTracer $tracer ) {
			assert( $el instanceof DOMElement );

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

			self::assertNull(
				$tracer->getCurrentIterationCountOf( Table::Head ),
				'Head count is not accessible when inferring <tr> content.'
			);

			return new ArrayObject( $result );
		};

		$tdAsserter = static function ( string|DOMElement $el, int $pos, TableTracer $tracer ) {
			self::assertInstanceOf( DOMElement::class, $el );
			self::assertNull(
				$tracer->getCurrentIterationCountOf( Table::Head ),
				'Head count is not accessible when inferring <td> content.'
			);

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

		$scanner->withAllTables()
			->transformWith( new $transformer( $captionAsserter ), Table::Caption )
			->transformWith( new $transformer( $thAsserter ), Table::Head )
			->transformWith( new $transformer( $trAsserter ), Table::Row )
			->transformWith( new $transformer( $tdAsserter ), Table::Column )
			->inferTableFrom( $dom->childNodes );

		$this->assertSame(
			array( 'text1' => 'This is a', 'bold1' => 'Caption', 'text2' => 'content' ),
			json_decode( $scanner->getTableCaption()[ $scanner->getTableId()[0] ] ?? '', associative: true )
		);
	}

	/** @param TableTracer<string,string> $tracer */
	private static function assertKeyAndPositionInTH(
		TableTracer $tracer,
		string $headContent,
		int $expectedPosition,
		int $actualPosition
	): void {
		self::assertTrue( str_ends_with( $headContent, (string) $expectedPosition ) );
		self::assertSame( $expectedPosition, $actualPosition );
		self::assertSame( $expectedPosition + 1, $tracer->getCurrentIterationCountOf( Table::Head ) );
	}

	/** @param TableTracer<string,string> $tracer */
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
		$table = '<table><caption>This is caption</caption><thead><tr><th>This is head</th></tr></thead></table>';

		$scanner = new DOMNodeScanner();

		$scanner->inferTableFrom( DOMDocumentFactory::createFromHtml( $table )->childNodes );

		$this->assertEmpty( $scanner->getTableId() );
	}
}


// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
/** @template-implements TableTracer<string,string> */
class DOMNodeScanner implements TableTracer {
	/** @use HtmlTableFromNode<string,string> */
	use HtmlTableFromNode;

	/** @param Closure(DOMElement, self): bool $validator */
	public function __construct( private ?Closure $validator = null ) {}

	/** @param DOMNodeList<DOMNode> $elementList */
	public function inferTableFrom( DOMNodeList $elementList ): void {
		$this->inferTableFromDOMNodeList( $elementList );
	}

	protected function isTargetedTable( DOMElement $node ): bool {
		return $this->validator ? ( $this->validator )( $node, $this ) : true;
	}
}
