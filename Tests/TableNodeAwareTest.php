<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use Closure;
use DOMElement;
use DOMDocument;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Scraper\Helper\Marshaller;
use TheWebSolver\Codegarage\Scraper\Traits\TableNodeAware;
use TheWebSolver\Codegarage\Scraper\Traits\DOMAttributeAware;

class TableNodeAwareTest extends TestCase {
	private const HTML = <<<WITH_TABLE
	<div id="content">
		<table id="developer-list" class="sortable collapsible">
			<caption>
				Some caption<b id="some">bold</b>
			</caption>
			<tbody>
				<tr>
					<th>Name</th>
					<th>
						<span class="nowrap">Title</span>
					</th>
					<th>
						<span>
							Address
								<a href="#location-anchor">&#91;b&#93;</a>
						</span>
					</th>
				</tr>
				<tr>
					<td>John Doe</td>
					<td>PHP Developer</td>
					<td>
						<a
							href="/location"
							title="Developer location"
							>Ktm</a
						>
					</td>
				</tr>
				<tr>
					<td>Lorem Ipsum</td>
					<td>JS Developer</td>
					<td>
						<table id="inside-td">
							<tbody>
								<tr>
									<td>Bkt</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
			</tbody>
		</table>
		<div id="inner-content">
			<div class="table-wrapper">
				<table id="inner-content-table">
					<tbody>
						<tr>
							<td>1: First Data</td>
							<td>2: Second Data</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
	WITH_TABLE;

	private function getLoadedDom(): DOMDocument {
		$dom = new DOMDocument();

		$dom->loadHTML( self::HTML, LIBXML_NOERROR | LIBXML_NOBLANKS );

		return $dom;
	}

	#[Test]
	public function itOnlyScansTargetedTable(): void {
		$dom     = $this->getLoadedDom();
		$scanner = new DOMNodeScanner(
			static function ( DOMElement $node, DOMNodeScanner $scanner ) {
				return $scanner->domElementHasId( $node, id: 'inner-content-table' );
			}
		);

		$scanner->scanTableBodyNodeIn( $dom->childNodes );

		$this->assertCount( 0, $scanner->getTableIds(), 'Cannot scan without marshaller' );

		$scanner
			->useMarshaller(
				( new Marshaller( 'td' ) )->marshallWith(
					static fn ( string|DOMElement $node )
						=> substr( $node instanceof DOMElement ? $node->textContent : $node, offset: 3 )
				)
			)
			->withOnlyContents()
			->scanTableBodyNodeIn( $dom->childNodes );

		$this->assertCount( 1, $tableIds = $scanner->getTableIds() );
		$this->assertCount( 2, $tds = $scanner->getTableData()[ $tableIds[0] ] );
		$this->assertSame( array( 'First Data', 'Second Data' ), $tds->getArrayCopy() );
	}

	#[Test]
	public function itGetsTheTargetedTableNode(): void {
		$dom               = new DOMDocument();
		$handler           = new DOMNodeScanner();
		$dom->formatOutput = false;

		$this->assertTrue( $dom->loadHTML( self::HTML, LIBXML_NOERROR | LIBXML_NOBLANKS ) );

		$thMarshaller = function ( string|DOMElement $e ) {
			return explode( '[', $e instanceof DOMElement ? $e->textContent : $e )[0];
		};

		$handler
			->useMarshaller(
				( new Marshaller( 'th' ) )->marshallWith( $thMarshaller ),
				( new Marshaller( 'td' ) )
			)->withAllTableNodes()
			->scanTableBodyNodeIn( $dom->childNodes );

		$this->assertCount( 3, $handler->getTableIds() );

		$tableId = $handler->getTableIds()[0];
		$th      = $handler->getTableHead( true )[ $tableId ]->toArray();
		$data    = $handler->getTableData();
		$td      = $data[ $tableId ]->getArrayCopy();

		$this->assertSame( array( 'Name', 'Title', 'Address' ), $th );
		$this->assertEqualsCanonicalizing( array_keys( $td ), $th );
	}
}

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
class DOMNodeScanner {
	use TableNodeAware, DOMAttributeAware;

	/** @param Closure(DOMElement, self): bool $validator */
	public function __construct( private ?Closure $validator = null ) {}

	protected function isTargetedTable( DOMElement $node ): bool {
		return $this->validator ? ( $this->validator )( $node, $this ) : true;
	}
}
