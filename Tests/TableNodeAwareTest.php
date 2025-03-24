<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use Closure;
use DOMElement;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\AssertDOMElement;
use TheWebSolver\Codegarage\Scraper\DOMDocumentFactory;
use TheWebSolver\Codegarage\Scraper\Traits\TableNodeAware;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

class TableNodeAwareTest extends TestCase {
	final public const TABLE_SOURCE = __DIR__ . DIRECTORY_SEPARATOR . 'Resource' . DIRECTORY_SEPARATOR . 'table.html';

	#[Test]
	public function itOnlyScansTargetedTable(): void {
		$dom        = DOMDocumentFactory::createFromHtml( self::TABLE_SOURCE );
		$innerTable = static function ( DOMElement $node ) {
			return AssertDOMElement::hasId( $node, id: 'inner-content-table' );
		};

		$scanner = new DOMNodeScanner( $innerTable );

		$scanner->traceTableIn( $dom->childNodes );

		$this->assertCount( 1, $tableIds = $scanner->getTableId() );
		$this->assertCount( 2, $scanner->getTableData()[ $tableIds[0] ]->current() );
	}

	#[Test]
	public function itGetsTheTargetedTableNode(): void {
		$handler      = new DOMNodeScanner();
		$dom          = DOMDocumentFactory::createFromHtml( self::TABLE_SOURCE );
		$thMarshaller = new class() implements Transformer {
			public function transform( string|DOMElement $element, int $position ): string {
				return explode( '[', $element instanceof DOMElement ? $element->textContent : $element )[0];
			}
		};

		$handler
			->withTransformers( array( 'th' => $thMarshaller ) )
			->withAllTables()
			->traceTableIn( $dom->childNodes );

		$ids       = $handler->getTableId();
		$th        = $handler->getTableHead( true )[ $ids[0] ]->toArray();
		$data      = $handler->getTableData();
		$devTable  = $data[ $ids[0] ];
		$dataTable = $data[ $ids[1] ];

		$this->assertSame( array( 'Name', 'Title', 'Address' ), $th );
		$this->assertEqualsCanonicalizing( array_keys( $first = $devTable->current()->getArrayCopy() ), $th );
		$this->assertSame( array( 'John Doe', 'PHP Developer', 'Ktm' ), array_values( $first ) );

		$devTable->next();

		$this->assertCount( 3, $handler->getTableId(), 'Third table inside second <tr> element.' );

		$this->assertSame(
			array( 'Lorem Ipsum', 'JS Developer', 'Bkt' ),
			array_values( $devTable->current()->getArrayCopy() )
		);

		$this->assertSame(
			array( '1: First Data', '2: Second Data' ),
			array_values( $dataTable->current()->getArrayCopy() )
		);

		$addressTableId = $handler->getTableId( true );

		$this->assertSame( 'Bkt', $handler->getTableData()[ $addressTableId ]->current()->getArrayCopy()[0] );
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

		$scanner = new DOMNodeScanner();

		$scanner->traceTableIn( DOMDocumentFactory::createFromHtml( $table )->childNodes );

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
	}

	#[Test]
	#[DataProvider( 'provideInvalidHtmlTable' )]
	public function itParsesInvalidTableGracefully( string $html, bool $hasHead = false ): void {
		$scanner = new DOMNodeScanner();

		$scanner->traceTableIn( DOMDocumentFactory::createFromHtml( $html )->childNodes );

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
			public function transform( string|DOMElement $element, int $position ): string {
				$content = $element instanceof DOMElement ? $element->textContent : $element;

				return str_contains( $content, 'Two' ) ? '' : $content;
			}
		};

		$scanner->withTransformers( array( 'td' => $tdMarshaller ) )
			->traceTableIn( DOMDocumentFactory::createFromHtml( $table )->childNodes );

		$this->assertNotEmpty( $data = $scanner->getTableData()[ $scanner->getTableId()[0] ]->current()->getArrayCopy() );
		$this->assertSame( 2, $scanner->getCurrentIterationCountOf( Table::Column ) );
		$this->assertArrayHasKey( 'First', $data );
		$this->assertArrayNotHasKey( 'Last', $data, 'Skips falsy (empty string) transformed value.' );
	}

	#[Test]
	public function itCascadesParentCurrentCountIfTdHasTable(): void {
		$table = '
			<table><tbody>
				<tr><th>Top 0</th><th><!-- comment -->Top 1</th><th><!-- comment --><!-- comment -->Top 2</th><!-- comment --><!-- comment --></tr>
				<tr>
				<!-- comment -->
				<!-- comment -->
					<td>0</td>
					<!-- comment -->
					<!-- comment -->
					<!-- comment -->
					<td>1
						<!-- comment -->
						<table><tbody>
							<tr><th><!-- comment --><!-- comment -->Middle 0</th><th><!-- comment -->Middle 1</th><th>Middle 2</th></tr>
							<tr>
								<!-- comment -->
								<td>zero:</td>
								<!-- comment -->
								<!-- Inner table -->
								<td>one:<!-- comment -->
									<!-- Final table -->
									<div>
										<table><tbody>
											<tr><th>Last 0</th><th><!-- comment --><!-- comment -->Last 1</th></tr>
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

		$scanner  = new DOMNodeScanner();
		$asserter = static function ( string|DOMElement $el, int $pos ) use ( $scanner ) {
			self::assertInstanceOf( DOMElement::class, $el );

			$text = $el->textContent;

			match ( true ) {
				default => throw new \LogicException( 'This should never be thrown. All tables are covered.' ),

				str_starts_with( $text, '0' )  => self::assertKeyAndPosition( $scanner, 'Top 0', 0, $pos ),
				str_starts_with( $text, '1' )  => self::assertKeyAndPosition( $scanner, 'Top 1', 1, $pos ),
				str_starts_with( $text, '2' )  => self::assertKeyAndPosition( $scanner, 'Top 2', 2, $pos ),

				str_starts_with( $text, 'zero:' ) => self::assertKeyAndPosition( $scanner, 'Middle 0', 0, $pos ),
				str_starts_with( $text, 'one:' )  => self::assertKeyAndPosition( $scanner, 'Middle 1', 1, $pos ),
				str_starts_with( $text, 'two:' )  => self::assertKeyAndPosition( $scanner, 'Middle 2', 2, $pos ),

				str_starts_with( $text, 'O=' ) => self::assertKeyAndPosition( $scanner, 'Last 0', 0, $pos ),
				str_starts_with( $text, 'I=' ) => self::assertKeyAndPosition( $scanner, 'Last 1', 1, $pos ),
			};

			return $text;
		};

		$tdTransformer = new class( $asserter ) implements Transformer {
			public function __construct( private Closure $asserter ) {}

			public function transform( string|DOMElement $element, int $position ): string {
				( $this->asserter )( $element, $position );

				return $element instanceof DOMElement ? $element->textContent : $element;
			}
		};

		$scanner->withAllTables()
			->withTransformers( array( 'td' => $tdTransformer ) )
			->traceTableIn( DOMDocumentFactory::createFromHtml( $table )->childNodes );
	}

	private static function assertKeyAndPosition(
		DOMNodeScanner $scanner,
		string $key,
		int $expectedPosition,
		int $actualPosition
	): void {
		self::assertSame( $key, $scanner->getCurrentColumnName() );
		self::assertSame( $expectedPosition, $actualPosition );
		self::assertSame( $expectedPosition + 1, $scanner->getCurrentIterationCountOf( Table::Column ) );
	}
}


// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
/** @template-implements TableTracer<string,string> */
class DOMNodeScanner implements TableTracer {
	/** @use TableNodeAware<string,string> */
	use TableNodeAware;

	/** @param Closure(DOMElement, self): bool $validator */
	public function __construct( private ?Closure $validator = null ) {}

	protected function isTargetedTable( DOMElement $node ): bool {
		return $this->validator ? ( $this->validator )( $node, $this ) : true;
	}
}
