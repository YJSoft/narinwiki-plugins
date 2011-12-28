<?
/**
 *
 * 나린위키 semantic wiki 플러그인 : 문법 클래스
 *
 * @package	   narinwiki
 * @subpackage plugin
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Jin Jun (jinjunkr@gmail.com)
 */
class NarinSyntaxSemantic extends NarinSyntaxPlugin {
	
	/**
	 *
	 * @var string 사용되는 디비 테이블 이름
	 */
	var $db_table;
	
	/**
	 * 
	 * semantic keyword
	 * @var string
	 */
	var $semantic_class = "";
	
	/**
	 * 
	 * semantic properties
	 * @var array
	 */
	var $semantic_properties = array ();
	
	/**
	 * 
	 * semantic name filter
	 * @var string
	 */
	var $semantic_wr_id = -1;
	
	/**
	 * 
	 * 파싱 시작되기 전에 변수 초기화
	 */
	function init()
	{
		$this->db_table = "byfun_narin_dataplugin";
	}
	
	/**
	 *
	 * @see lib/NarinSyntaxPlugin::register()
	 */
	function register($parser)
	{
		$parser->addWordParser(
			$id = $this->plugin_info->getId()."_semantic_class",
			$klass = $this,
			$regx = "~~SEMANTIC(:([^~]*?))?~~",
			$method = "semantic_class");
		
		$parser->addWordParser(
			$id = $this->plugin_info->getId()."_semantic_property",
			$klass = $this,
			$regx = "::(.*?):(.*?)::",
			$method = "semantic_property");
		
		$parser->addWordParser(
			$id = $this->plugin_info->getId()."_semantic_inlineout",
			$klass = $this,
			$regx = ";;(.*?):(.*?)(:(.*?))?;;",
			$method = "semantic_inlineout");
		
		// semantic box printout
		$parser->addEvent(EVENT_AFTER_PARSING_ALL, $this, "semantic_box");
		
	}

	
	/**
	 * 
	 * semantic class define
	 * 
	 * @param array $matches 패턴매칭 결과
	 * @param array $params {@link NarinParser} 에서 전달하는 파라미터
	 * @return
	 */
	public function semantic_class($matches, $params) {
		$this->semantic_class = $matches[2];
		return "";
	}
	
	/**
	 * 
	 * semantic property define
	 * 
	 * @param array $matches 패턴매칭 결과
	 * @param array $params {@link NarinParser} 에서 전달하는 파라미터
	 * @return
	 */
	public function semantic_property($matches, $params) {
		$type = $matches[1];
		$val  = $matches[2];
		// real data insertion should be done in action.php
		
		// keep the data for showing semantic box 
		array_push($this->semantic_properties, array("type"=>$type, "val"=>$val) );
		
		// some types can be replaced with specific form
		//TODO: other types
		if(preg_match('/_page$/', $type)) 
		// TODO: don't know if the page exists.. class=wiki_active_link or wiki_inactive_link
			return "<a href='".$this->wiki['path']."/narin.php?bo_table=".$this->bo_table."&doc=".urlencode($val)."'>".$val."</a>";
		return $val;
	}

	/**
	 * 
	 * semantic property printout
	 * 
	 * @param array $matches 패턴매칭 결과
	 * @param array $params {@link NarinParser} 에서 전달하는 파라미터
	 * @return
	 */
	public function semantic_inlineout($matches, $params) {
		// matches[1] : keyword
		// matches[2] : 2nd param: could be filter(e.g. name=NewYork), filter w/o key (e.g. NewYork) with 3rd param, or just key once filter set
		// matches[4] : 3rd param: key with 2nd param used with filter
	
		$keyword = $matches[1];
	
		// parse options
		$filter_keyword = " keyword = '".$keyword."' ";
		$requested_col = "";

		// if matches[2] is 1) filter, 2) filter(name=) with 3rd val, 3) or just val
		$requested_wr_id = -1;
		if(preg_match('/^(.*)=(.*)$/', $matches[2], $kv)) {		// case 1
			$filter_where = " col = '".$kv[1]."' AND val = '".$kv[2]."' ";
			$sql_wr_id = "SELECT wr_id FROM ".$this->db_table."
							WHERE bo_table='".$this->bo_table."' AND ".$filter_keyword." AND ".$filter_where;
			$res_wr_id = sql_query($sql_wr_id);
			if(mysql_num_rows($res_wr_id)>0) {
				$row_wr_id = mysql_fetch_assoc($res_wr_id);
				$this->semantic_wr_id = $row_wr_id['wr_id'];
			}
			if($matches[4]) $requested_col = $matches[4];
			else $requested_col = $kv[1];
		}elseif($matches[4]) {				// case 2; for now use 'name' or '이름'
			$filter_where = " ( col = 'name' AND val = '".$matches[2]."' OR col = '이름' AND val = '".$matches[2]."' ) ";
			$sql_wr_id = "SELECT wr_id FROM ".$this->db_table."
							WHERE bo_table='".$this->bo_table."' AND ".$filter_keyword." AND ".$filter_where;
			$res_wr_id = sql_query($sql_wr_id);
			if(mysql_num_rows($res_wr_id)>0) {
				$row_wr_id = mysql_fetch_assoc($res_wr_id);
				$requested_wr_id = $row_wr_id['wr_id'];
			}
			$requested_col = $matches[4];
		}else {								// case 3
			$requested_col = $matches[2];
		}
		
		if($requested_wr_id < 0 && $this->semantic_wr_id > 0) $requested_wr_id = $this->semantic_wr_id;
		
		if($requested_wr_id > 0) {		// either temp one set, or $this->semantic one set
					
			// fullpath is reserved for %pageid%, docname for %title%, keyword for %class%
			if($requested_col=='%pageid%' || $requested_col=='%title%') {
				$wikiArticle =& wiki_class_load("Article");
				$write = $wikiArticle->getArticleById($requested_wr_id);
				$fullpath = wiki_doc($write['ns'], $write['doc']);
				$href = $this->wiki['path']."/narin.php?bo_table=".$this->bo_table."&doc=".urlencode($fullpath);
				if($requested_col=='%pageid%') {
					return "<a href='".$href."' class='wiki_active_link'>".$fullpath."</a>";
				}else {
					return "<a href='".$href."' class='wiki_active_link'>".$write['doc']."</a>";
				}
			}else {
				$sql = "SELECT val FROM ".$this->db_table."
							WHERE bo_table='".$this->bo_table."' AND ".$filter_keyword." AND col='".$requested_col."' AND wr_id=".$requested_wr_id."
							LIMIT 1";
				$row = sql_fetch($sql);
				if(!$row['val']) return " <span style='color:red;'>등록정보없음</span> ";
				return $row['val'];
			}
		}
		return " <span style='color:red;'>등록정보없음</span> ";
	}
	
	
	/**
	 *
	 * semantic box 출력 (after parsing)
	 *
	 * @param array $params {@link NarinParser} 에서 전달하는 파라미터
	 */
	public function semantic_box($params) {
		if(!$this->semantic_class) return;
		
		$line_gap = '<tr style="height: 2px;"><td></td></tr>';
		
		$content = '
		<style>
table.navbox {
border: 1px solid #AAA;
width: 98%;
margin: 5em 1em 0 1em;
clear: both;
font-size: 8px !important;
text-align: center;
padding: 1px;
}
.navbox-title, .navbox th {
background: #CCF;
text-align: center;
padding-left: 1em;
padding-right: 1em;
font-family: 맑은 고딕;
font-size: 10px !important;
}
.navbox th {
align: right;
}
.navbox td {
font-family: 맑은 고딕;
font-size: 9px !important;
}
.navbox .collapseButton {
width: 6em;
float: right;
font-weight: normal;
margin-left: 0.5em;
text-align: right;
width: auto;
}
.navbox-odd {
background: transparent;
}
.navbox-even {
background: #F7F7F7;
}
.navbox-list {
border-color: #FDFDFD;
}
.hlist dd::after, .hlist li::after {
content: " ·";
font-weight: bold;
}
</style>
		<table class="navbox" cellspacing="0" style=";">
		<tbody><tr>
		<td style="padding:2px;">
		<table cellspacing="0" class="collapsible collapsed" style="width:100%;background:transparent;color:inherit;;" id="collapsibleTable0">
		<tbody><tr>
		<th scope="col" style=";" class="navbox-title" colspan="2"><span class="collapseButton">[<a id="collapseButton0" href="#">hide</a>]</span>
		<div class="" style="font-size:110%;"><strong class="selflink">'.$this->semantic_class.'</strong></div>
		</th>
		</tr>
		';

		$num = 1;
		foreach($this->semantic_properties as $p) {
			if($num % 2 == 1) $line = "odd";
			else $line = "even";
			$num++;
			
			$content .= $line_gap.'
		<tr style="">
		<th scope="row" class="navbox-group" style=";">'.$p['type'].'</th>
		<td style="text-align:left;border-left-width:2px;border-left-style:solid;width:100%;padding:0px;;;" class="navbox-list navbox-'.$line.' hlist">
		<div style="padding:0em 0.25em">';
			
			// make list if $p->type has many vals..
/*			
		<ul>
		<li><a href="/wiki/World_Wide_Web" title="World Wide Web">World Wide Web</a></li>
		<li><a href="/wiki/Internet" title="Internet">Internet</a></li>
		<li><a href="/wiki/Hypertext" title="Hypertext">Hypertext</a></li>
		<li><a href="/wiki/Database" title="Database">Databases</a></li>
		<li><a href="/wiki/Semantic_network" title="Semantic network">Semantic networks</a></li>
		<li><a href="/wiki/Ontology_(information_science)" title="Ontology (information science)">Ontologies</a></li>
		</ul>
*/
			// unless just $p->val
			$content .= $p['val'];
			$content .= '
		</div>
		</td>
		</tr>';
		}

		$content .= '
		</tbody></table>
		</td>
		</tr>
		</tbody></table>';
				
		$params['output'] = $params['output'].$content;
	}
	
		
	
	/**
	 *
	 * inline 형태의 출력: datacount / datalist
	 *
	 * @param array $matches 패턴매칭 결과
	 * @param array $params {@link NarinParser} 에서 전달하는 파라미터
	 * @return string output
	 */
	public function wiki_datainline($matches, $params) {
		// matches[1] : method (count or list)
		// matches[5] : list of parameter=value after '?'

		$method = $matches[1];
		
		// parse options
		$filter_keyword = "1";
		$filters = array();
		
		// for datalist
		$field = 'fullpath';
		
		if($matches[5]) { 
			$list = explode("&", str_replace("&amp;", "&", $matches[5]));
			foreach($list as $el) {
				$op_pattern = '/^(.*?)((=|\!=|&lt;&gt;|&lt;|&lt;=|=&gt;|&gt;|\~|\!\~)(.*))?$/s';
				preg_match($op_pattern, $el, $op_matches);
				$col = $op_matches[1];
				$val = $op_matches[4];
				$op  = $op_matches[3];
				$not = "";
				if(!$op_matches[2]) {	// no comparison.. so class
					// datalist 인경우는 keyword:field 형태로 한 field를 선택, 없는 경우는 %pageid%
					if($method == "list" || $method == "item") {
						$kv = explode(":", $col);
						$val = $kv[0];
						if($kv[1]) $field = $kv[1];
					}else {
						$val = $op_matches[1];
					}
					$col = 'class';					
					$op = '=';
				}else {
					if($op=='~') {	// wildcard
						$op = 'LIKE';
						$val = preg_replace('/\*/', '%', $val);
					}elseif($op=='!~') {	// wildcard
						$op = 'LIKE';
						$not = 'NOT';
						$val = preg_replace('/\*/', '%', $val);
					}elseif($op=='!=' || $op=='<>') {
						$op = '=';
						$not = 'NOT';
					}else {
						$op = preg_replace('/&lt;/','<',$op);
						$op = preg_replace('/&gt;/','>',$op);
					}
				}
				
				if($col == 'class') {
					$keyword = 'class';
					$filter_keyword = $not." keyword ".$op." '".$val."' ";
				}
				else array_push($filters, "(col = '".$col."' AND ".$not." val ".$op." '".$val."')");
			}
		}
		
		// filtering
		$filter_where = "1";
		if(count($filters)) $filter_where = implode(' AND ', $filters);
		
		$sql_wr_id = "SELECT DISTINCT wr_id FROM ".$this->db_table."
								WHERE bo_table='".$this->bo_table."' AND ".$filter_keyword." AND ".$filter_where;
		$res_wr_id = sql_query($sql_wr_id);
		if($method == "count") {
			return " ".mysql_num_rows($res_wr_id)." ";
		}else {
			$data_array = array();
			while($row_wr_id = mysql_fetch_assoc($res_wr_id)) {
				$wr_id = $row_wr_id['wr_id'];
			
				// fullpath is reserved for %pageid%, docname is reserved for %title%
				if($field=='fullpath' || $field=='%pageid%' || $field=='%title%') {
					$wikiArticle =& wiki_class_load("Article");
					$write = $wikiArticle->getArticleById($wr_id);
					$fullpath = wiki_doc($write['ns'], $write['doc']);
					$href = $this->wiki['path']."/narin.php?bo_table=".$this->bo_table."&doc=".urlencode($fullpath);
					if($field=='fullpath' || $field=='%pageid%') {
						array_push($data_array, "<a href='".$href."' class='wiki_active_link'>".$fullpath."</a>");
					}else {
						array_push($data_array, "<a href='".$href."' class='wiki_active_link'>".$write['doc']."</a>");
					}
				}else {
					$sql = "SELECT val FROM ".$this->db_table."
								WHERE bo_table='".$this->bo_table."' AND ".$filter_keyword." AND col='".$field."' AND wr_id=".$wr_id."
								GROUP BY wr_id";
					$row = sql_fetch($sql);
					array_push($data_array, $row['val']);
				}
				if($method == "item") {
					return " ".array_shift($data_array)." ";
				}
			}
			if(count($data_array) == 0) return " <span style='color:red;'>등록정보없음</span> ";
			sort($data_array);
			return " ".implode(", ", $data_array)." ";
		}
	}
		
	/**
	 *
	 * datatable/datalist 출력
	 *
	 * @param array $matches 패턴매칭 결과
	 * @param array $params {@link NarinParser} 에서 전달하는 파라미터
	 * @return string output
	 */
	public function wiki_dataout($matches, $params) {
		// $matches[1] = table or list
		// $matches[2] = content
	
		$args = array();
		
		$args['type'] = $matches[1];
		$content = $matches[2];
		
		// content format
		// cols    : %pageid%, name, description, author, lastupdate_dt
		// headers : Details, 플러그인 이름, 설명, 저자, 최종수정일
		// max     : 10
		// filter  : class=plugins
		// sort    : ^name
		
		$fields = array();
		$args['headers'] = array();	// only works for table
		$args['max'] = -1;
		$keyword = '';
		$filter_keyword = '1';
		$filters = array();
		$sort_dir = 'ASC';
		$sort_col = '';
		
		$lines = preg_split( '/\r\n|\r|\n/', $content);
		foreach($lines as $line) {
			$kv = array_map('trim', explode(':', $line, 2));
			switch($kv[0]) {
				case 'cols':
				case 'col':
					$cols = array_map('trim', explode(',', $kv[1]));
					foreach($cols as $col) {
						switch($col) {
							case '%pageid%':
								array_push($fields, 'fullpath');
								break;
							case '%title%':
								array_push($fields, 'docname');
								break;
							case '%class%':
								array_push($fields, 'class');
								break;
							default:
								array_push($fields, $col);
						}
					}
					break;
				case 'headers':
				case 'header':
				case 'head':
					$args['headers'] = array_map('trim', explode(',', $kv[1]));
					break;
				case 'max':
				case 'limit':
					$args['max'] = $kv[1];
					break;
				case 'filter':
				case 'where':
				case 'fiterand':
				case 'and':
					$op_pattern = '/^(.*?)(=|\!=|&lt;&gt;|&lt;|&lt;=|=&gt;|&gt;|\~|\!\~)(.*)$/s';
					preg_match($op_pattern, $kv[1], $op_matches);
					$col = $op_matches[1];
					$val = $op_matches[3];
					$op  = $op_matches[2];
					$not = "";
					if($op=='~') {	// wildcard
						$op = 'LIKE';
						$val = preg_replace('/\*/', '%', $val);
					}elseif($op=='!~') {	// wildcard
						$op = 'LIKE';
						$not = 'NOT';
						$val = preg_replace('/\*/', '%', $val);
					}elseif($op=='!=' || $op=='<>') {
						$op = '=';
						$not = 'NOT';
					}else {
						$op = preg_replace('/&lt;/','<',$op);
						$op = preg_replace('/&gt;/','>',$op);
					}
					 
					if($col == 'class') {
						$keyword = 'class';
						$filter_keyword = $not." keyword ".$op." '".$val."' ";
					}
					else array_push($filters, "(col = '".$col."' AND ".$not." val ".$op." '".$val."')");
					break;
				case 'sort':
				case 'order':
					if(preg_match('/^\^/',$kv[1])) {
						$sort_dir = 'DESC';
					}
					$sort_col = trim($kv[1],'^');
					break;
			}
		}
		$args['fields'] = $fields;
		// if headers are not specified, use col($fields) instead
		if(!$args['headers']) $args['headers'] = $args['fields'];
		
		$wikiArticle =& wiki_class_load("Article");
		// retrieve the data
		$list = array();
		$list_sort = array();
		
		// filtering
		$filter_where = "1";
		if(count($filters)) $filter_where = implode(' AND ', $filters);
				
		// currently dataentry without keyword might have a problem to show
		$sql_wr_id = "SELECT DISTINCT wr_id FROM ".$this->db_table." 
						WHERE bo_table='".$this->bo_table."' AND ".$filter_keyword." AND ".$filter_where;
		$res_wr_id = sql_query($sql_wr_id);
		while($row_wr_id = sql_fetch_array($res_wr_id)) {
			$data = array();
			
			$wr_id = $row_wr_id['wr_id'];
			$write = $wikiArticle->getArticleById($wr_id);
			
			foreach($fields as $field) {
				// fullpath is reserved for %pageid%, docname is reserved for %title%
				if($field=='fullpath' || $field=='docname') {
					$data['fullpath'] = wiki_doc($write['ns'], $write['doc']);
					$data['docname'] = $write['doc'];
					continue;
				}
				
				// class is reserved for %class%
				if($field == 'class') {
					// not much special treatment.. unless providing dynamic datatable page for specific class
					$data['class'] = $keyword;
					continue;
				}
				
				// other fields, potentially multiple values
				$sql = "SELECT col, val FROM ".$this->db_table." WHERE bo_table='".$this->bo_table."' AND wr_id=".$wr_id." AND ".$filter_keyword." AND col='".$field."'";
				$res = sql_query($sql);
				$val_array = array();
				while($row = sql_fetch_array($res)) {
					array_push($val_array, $row['val']);
				}
				$data[$field] = implode(", ", $val_array);
				
				// store the values for sorting.. for now %..% fields cannot be used for sorting.. WTH
				if($field == $sort_col) array_push($list_sort, $data[$field]);
			}
			array_push($list, $data);
		}
		
		// sorting
		if($sort_col) {
			if($sort_dir == 'ASC') {
				array_multisort($list_sort, SORT_ASC, $list);
			}else {
				array_multisort($list_sort, SORT_DESC, $list);
			}
		}		
		
		if($args['type'] == 'table' || $args['type'] == 'stable') return $this->render_table($args, &$list, &$params);
		else return $this->render_list($args, &$list, &$params);
	}	

	
	/**
	 * 
	 * 목록 형태로 출력
	 * 
	 * @param array $args 최근문서문법에서 분석된 파라미터
	 * @param array $list 최근문서목록
	 * @param array $params {@link NarinParser} 에서 전달하는 파라미터
	 * @return string HTML 태그
	 */
	protected function render_list($args, $list, $params) {	
		$ret = "";
		
		$count = 0;
		foreach($list as $li) {
			$count ++;
			$ret .= "  * ";
			$set_array = array();	// just for eye candy
			foreach($args['fields'] as $field) {
				$content = $li[$field];
		
				if($field == "fullpath") {
					$content = "[[".$li['fullpath']."|".$li['fullpath']." ]]";
				}elseif($field == "docname") {
					$content = "[[".$li['fullpath']."|".$li['docname']." ]]";
				}else if(preg_match('/_page$/',$field)) {
					$pages = array_map('trim', explode(',', $li[$field]));
					$link_array = array();
					foreach($pages as $page) {
						array_push($link_array, "[[".$page."]]");
					}
					$content = implode(", ", $link_array);
				}
				
				array_push($set_array, $content);
			}
			$ret .= implode("<span style='color:gray; padding: 0 5px;'>|</span>", $set_array)."\n";
			if($args['max']>0 && $count>=$args['max']) break;
		}
		
		unset($list);
		return $ret;
	}
	
	/**
	 * 
	 * 테이블 형태로 출력
	 * 
	 * @param array $args 최근문서문법에서 분석된 파라미터
	 * @param array $list 최근문서목록
	 * @param array $params {@link NarinParser} 에서 전달하는 파라미터
	 * @return string HTML 태그
	 */
	protected function render_table($args, $list, $params) {
		$openline = "";
		$closeline = "\n";
		if($args['type'] == 'stable' && $this->sortableTablePlugin) {
			$openline = "(";
			$closeline = ")\n";
		}
		$ret = $openline."^";
		foreach($args['headers'] as $header) {
			$ret .= " ".$header." ^";
		}
		$ret .= $closeline;
		
		$count = 0;
		foreach($list as $tr) {
			$count ++;
			$ret .= $openline."|";
			foreach($args['fields'] as $field) {
				$content = $tr[$field];
								
				if($field == "fullpath") {
					$content = "[[".$tr['fullpath']."|".$tr['fullpath']." ]]";
				}elseif($field == "docname") {
					$content = "[[".$tr['fullpath']."|".$tr['docname']." ]]";
				}else if(preg_match('/_page$/',$field)) {
					$pages = array_map('trim', explode(',', $tr[$field]));
					$link_array = array();
					foreach($pages as $page) {
						array_push($link_array, "[[".$page."]]");
					}
					$content = implode(", ", $link_array);
				}
				$ret .= " ".$content." |";
			}
			$ret .= $closeline;
			if($args['max']>0 && $count>=$args['max']) break;
		}
		
		unset($list);
		return $ret;
	}

}

?>