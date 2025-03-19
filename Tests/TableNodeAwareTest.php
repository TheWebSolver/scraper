<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use Closure;
use DOMElement;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Scraper\AssertDOMElement;
use TheWebSolver\Codegarage\Scraper\DOMDocumentFactory;
use TheWebSolver\Codegarage\Scraper\Marshaller\Marshaller;
use TheWebSolver\Codegarage\Scraper\Traits\TableNodeAware;

class TableNodeAwareTest extends TestCase {
	final public const TABLE_SOURCE = __DIR__ . DIRECTORY_SEPARATOR . 'Resource' . DIRECTORY_SEPARATOR . 'table.html';

	#[Test]
	public function itOnlyScansTargetedTable(): void {
		$dom        = DOMDocumentFactory::createFromHtml( self::TABLE_SOURCE );
		$innerTable = static function ( DOMElement $node ) {
			return AssertDOMElement::hasId( $node, id: 'inner-content-table' );
		};

		$scanner = new DOMNodeScanner( $innerTable );

		$scanner->scanTableNodeIn( $dom->childNodes );

		$this->assertCount( 1, $tableIds = $scanner->getTableIds() );
		$this->assertCount( 2, $scanner->getTableData()[ $tableIds[0] ][0] );

		$onlyContentScanner = new DOMNodeScanner( $innerTable );
		$tdMarshaller       = new Marshaller();
		$tdMarshaller->with(
			fn ( string|DOMElement $node )
			=> substr( $node instanceof DOMElement ? $node->textContent : $node, offset: 3 )
		);

		$onlyContentScanner
			->useTransformers( array( 'td' => $tdMarshaller ) )
			->scanTableNodeIn( $dom->childNodes );

		$this->assertSame(
			array( 'First Data', 'Second Data' ),
			$onlyContentScanner->getTableData()[ $onlyContentScanner->getTableIds()[0] ][0]->getArrayCopy()
		);
	}

	#[Test]
	public function itGetsTheTargetedTableNode(): void {
		$handler = new DOMNodeScanner();
		$dom     = DOMDocumentFactory::createFromHtml( self::TABLE_SOURCE );

		$thMarshaller = new Marshaller();

		$thMarshaller->with(
			fn ( string|DOMElement $e ) => explode( '[', $e instanceof DOMElement ? $e->textContent : $e )[0]
		);

		$handler
			->useTransformers( array( 'th' => $thMarshaller ) )
			->withAllTableNodes()
			->scanTableNodeIn( $dom->childNodes );

		$this->assertCount( 3, $handler->getTableIds() );

		$ids  = $handler->getTableIds();
		$th   = $handler->getTableHead( true )[ $ids[0] ]->toArray();
		$data = $handler->getTableData();

		$this->assertSame( array( 'Name', 'Title', 'Address' ), $th );
		$this->assertEqualsCanonicalizing( array_keys( $first = $data[ $ids[0] ][0]->getArrayCopy() ), $th );
		$this->assertSame( array( 'John Doe', 'PHP Developer', 'Ktm' ), array_values( $first ) );
		$this->assertSame(
			array( 'Lorem Ipsum', 'JS Developer', 'Bkt' ),
			array_values( $data[ $ids[0] ][1]->getArrayCopy() )
		);
		$this->assertSame(
			array( '1: First Data', '2: Second Data' ),
			array_values( $data[ $ids[2] ][0]->getArrayCopy() )
		);
	}

	#[Test]
	public function itScrapesDataFromTableHeadAndBodyElement(): void {
		$data = '
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

		$scanner->scanTableNodeIn( DOMDocumentFactory::createFromHtml( $data )->childNodes );

		$tableIds  = $scanner->getTableIds();
		$fixedHead = $scanner->getTableHead( namesOnly: true )[ $tableIds[0] ];
		$data      = $scanner->getTableData()[ $tableIds[0] ];

		$this->assertCount( 1, $tableIds );
		$this->assertCount( 2, $data );
		$this->assertSame( $headers = array( 'Title', 'Another Title' ), $fixedHead->toArray() );

		foreach ( $data as $index => $tableData ) {
			$value = 0 === $index ? array( 'Heading 1', 'Value One' ) : array( 'Heading 2', 'Value Two' );

			$this->assertSame( $headers, array_keys( $arrayCopy = $tableData->getArrayCopy() ) );
			$this->assertSame( $value, array_values( $arrayCopy ) );
		}
	}
}

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
class DOMNodeScanner {
	/** @use TableNodeAware<string,string> */
	use TableNodeAware;

	/** @param Closure(DOMElement, self): bool $validator */
	public function __construct( private ?Closure $validator = null ) {}

	protected function isTargetedTable( DOMElement $node ): bool {
		return $this->validator ? ( $this->validator )( $node, $this ) : true;
	}
}
