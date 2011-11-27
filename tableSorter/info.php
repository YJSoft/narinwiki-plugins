<?
/**
 * 나린위키 tableSorter 플러그인 : 플러그인 정보 클래스
 *
 * 사용법: 	
 * {{tableSorter=
 * 		^ Alphabetic ^ Numeric ^ Date ^ Unsortable ^
 * 		^ d | 20 | 2008-11-24 | This    |
 * 		^ b | 8  | 2004-03-01 | column  |
 * 		^ a | 6  | 1979-07-23 | cannot  |
 * 		^ c | 4  | 1492-12-08 | be      |
 * 		^ e | 0  | 1601-08-13 | sorted. |
 * }}
 * 
 * inspired by Mediawiki sortable class of wikitable, using jquery.tablesorter.js from http://tablesorter.com/
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Jin Jun (jinjunkr@gmail.com)
 */
 
class NarinPluginInfoTableSorter extends NarinPluginInfo {

	/**
	 * 생성자
	 */		  	
	public function __construct() {
		$this->id = "wiki_tableSorter";		
		parent::__construct();
	}	  	

	/**
	 * 플러그인 설명
	 */
	public function description()
	{
		return "Sortable table 플러그인 (저자 : Jin Jun, jinjunkr@gmail.com)";
	}
}



?>