<?
/**
 * 나린위키 sortableTable 플러그인 : 플러그인 정보 클래스
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Jin Jun (jinjunkr@gmail.com)
 */
 
class NarinSyntaxSortableTable extends NarinSyntaxPlugin {

	var $blocks = array();
	var $table_opened = false;
	var $table_theadOpened = false;
	
	/**
	 * 파싱 시작되기 전에 변수 초기화
	 */
	function init()
	{
		$this->blocks = array();
		$this->table_opened = false;
		$this->table_theadOpened = false;
	}

	/**
	 * 파서 등록
	 */	
	function register($parser)
	{
		$setting = $this->plugin_info->getPluginSetting();		
		
		$parser->addLineParser(
			$id = $this->plugin_info->getId()."_wiki_sortableTable",
			$klass = $this,
			$regx = '^\((\^|\|)(.*?)(\^|\|)\)$',
			$method = "wiki_sortableTable");

		$parser->addEvent(EVENT_AFTER_PARSING_ALL, $this, "wiki_sortableTable_after_all");
		$parser->addEvent(EVENT_AFTER_PARSING_LINE, $this, "wiki_sortableTable_after_parsing_line");
	}
	
	
	/**
	 * 라인 파싱을 모두 마치고
	 */
	public function wiki_sortableTable_after_all($params)
	{
		// 열린 table 이 있다면, 닫아줌
		if($this->table_opened) {
			$params[output] .= "</table>";	
		}
	}
	
	/**
	 * 한 라인 파싱을 마치고
	 */		
	public function wiki_sortableTable_after_parsing_line($params)
	{
		// 이전 라인에서 목록을 열었으면.. 닫음
		if ($this->table_opened && !$params[called][$this->plugin_info->getId()."_wiki_sortableTable"]) {
			$params[line] = $this->wiki_sortableTable(false, array(), true) . $params[line];
		}
    }
    
	/**
	 * 테이블 분석 - copied from narin.syntax.php (@author: byfun)
	 * 
	 * @format: 
	 * 		(^ Alphabetic ^ +Numeric ^ -Date ^ !Unsortable ^)
	 * 		(| d | 20 | 2008-11-24 | This    |)
	 * 		(| b | 8  | 2004-03-01 | column  |)
	 * 		(| a | 6  | 1979-07-23 | cannot  |)
	 * 		(| c | 4  | 1492-12-08 | be      |)
	 * 		(| e | 0  | 1601-08-13 | sorted. |)
	 */	
	public function wiki_sortableTable($matches, $params, $close = false) 
	{
  		if($close) {
			$this->table_opened = false;
			return "</table> <!--// wiki_table -->\n";
		}
	
		$parser = &$params[parser];
		$lines = &$params[lines];

		$line = $matches[0];
		$arr = preg_split("/(\^|\|)/", $line, -1, PREG_SPLIT_DELIM_CAPTURE  );
	
		// 불필요한 앞뒤 배열 제거
		array_shift($arr); array_pop($arr);array_pop($arr);
	
		$size = count($arr);
	
		// tr 태그 열기
		if(!$this->table_opened) {
			$out = "<table class=\"wiki_table tablesorter\" cellspacing=\"1\" cellpadding=\"0\">\n<thead>\n<tr>";
			$this->table_theadOpened = true;
		} else $out = "<tr>";
	
		$col = 0;
		for($i=0; $i<$size; $i++) {
			$value = trim($arr[$i]," !+-");	// for removing unsortable mark
			$open_tag = $this->get_table_tag($arr[$i], $close=false);
				
			if(!$open_tag) {	// 태그가 아닐 경우 값 과 닫는 태그 입력
				
				$close_tag = $this->get_table_tag($arr[$i-1], $close=true);
				
				$out .= $value;	// 값 입력
				$out .= $close_tag;	// 태그 닫음

			} else {
					
				// 정렬
				preg_match("/^([\s]*)([!\+\-]?)(.*?)([\s]*)$/", $arr[$i+1], $m);
				$tag = $this->get_table_tag($arr[$i], $close=false, $withBracket=false);
				$before_space = strlen($m[1]);
				$after_space = strlen($m[4]);
				$align = "";
	
				if( (!$before_space && !$after_space) || $before_space * $after_space > 0) $align = " align=\"center\"";
				else if($before_space && !$after_space) $align = " align=\"right\"";
				else $align = " align=\"left\"";
				
				// 칼럼
				$col = ($i/2+1);

				// unsortable
				$unsortable = $m[2]=="!" ? " unsortable " : "";
				
				// sorted
				$sorted = $m[2]=="+" ? " headerSortUp " : ( $m[2]=="-" ? " headerSortDown " : "");
	
				$class = " class=\"col".$col.$unsortable.$sorted."\"";
				
				$out .= "<$tag$align$class>";
			}
		}
	
		$out .= "</tr>\n";
		if($this->table_theadOpened) $out .= "</thead>\n";
		$this->table_theadOpened = false;
		$this->table_opened = true;
		$parser->stop = true;
		return $out;
	}
	
	/**
	 * Returns table tag (wiki_table 에서 사용)
	 */
	protected function get_table_tag($delim, $close=false, $withBracket=true)
	{
		if($withBracket) {
			$ot = "<";
			$ct = ">";
			$close = ($close ? "/" : "");
		} else $close = "";
	
		$delim = trim($delim);
	
		if($delim == "^") {
			return "$ot{$close}th$ct";
		}
		else if($delim == "|") {
			return "$ot{$close}td$ct";
		}
	}
}



?>