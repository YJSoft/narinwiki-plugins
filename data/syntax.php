<?
/**
 *
 * 나린위키 (structured) data 플러그인 : 문법 클래스
 *
 * @package	   narinwiki
 * @subpackage plugin
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Jin Jun (jinjunkr@gmail.com)
 */
class NarinSyntaxData extends NarinSyntaxPlugin {
	
	/**
	 *
	 * @var string 사용되는 디비 테이블 이름
	 */
	var $db_table;
	
	/**
	 * 
	 * sortableTable 플러그인 설치 여부
	 * @var true/false
	 */
	var $sortableTablePlugin = false;
	
	/**
	 * 
	 * template 플러그인설치 여부
	 * @var true/false
	 */
	var $templatePlugin = false;
	
	/**
	 * 
	 * template 이 있는 폴더
	 * @var string
	 */
	var $template_ns;
	
	
	/**
	 * 
	 * 파싱 시작되기 전에 변수 초기화
	 */
	function init()
	{
		$this->db_table = "byfun_narin_dataplugin";
		$wikiConfig =& wiki_class_load("Config");
		if(in_array('sortableTable', $wikiConfig->using_plugins)) {
			$this->sortableTablePlugin = true;
		}
		if(in_array('template', $wikiConfig->using_plugins)) {
			$this->templatePlugin = true;
			$setting = $this->plugin_info->getPluginSetting();
			$this->template_ns = $setting['template_ns']['value'];
		}
	}
	
	/**
	 *
	 * @see lib/NarinSyntaxPlugin::register()
	 */
	function register($parser)
	{
		// block dataentry
		$parser->addBlockParser(
			$id = $this->plugin_info->getId()."_wiki_dataentry",
			$klass = $this,
			$startRegx = "---- dataentry (.*?)----",
			$endRegx = "----",
			$method = "wiki_dataentry");

		// inline dataentry
		$parser->addVariableParser(
			$id = $this->plugin_info->getId()."_wiki_dataentry_inline",
			$klass = $this,
			$start_regx = 'dataentry=',
			$end_regx = '\?(.*)',
			$method = "wiki_dataentry_inline");
		
		// block dataout
		$parser->addBlockParser(
			$id = $this->plugin_info->getId()."_wiki_dataout",
			$klass = $this,
			$startRegx = "---- data(s?table|list) ----",
			$endRegx = "----",
			$method = "wiki_dataout");
		
		// inline dataout
		$parser->addVariableParser(
			$id = $this->plugin_info->getId()."_wiki_dataout_inline",
			$klass = $this,
			$start_regx = 'data(count|rank|list|item)(=)?',
			$end_regx = '(\?(.*?))?',
			$method = "wiki_dataout_inline");
		
		// future feature
		$parser->addBlockParser(
			$id = $this->plugin_info->getId()."_wiki_datacloud",
			$klass = $this,
			$startRegx = "---- datacloud ----",
			$endRegx = "----",
			$method = "wiki_datacloud");
		
		$parser->addBlockParser(
			$id = $this->plugin_info->getId()."_wiki_datarelated",
			$klass = $this,
			$startRegx = "---- datarelated ----",
			$endRegx = "----",
			$method = "wiki_datarelated");
		
	}

	
	/**
	 * 
	 * dataentry 등록
	 * 
	 * @param array $matches 패턴매칭 결과
	 * @param array $params {@link NarinParser} 에서 전달하는 파라미터
	 * @return
	 */
	public function wiki_dataentry($matches, $params) {
		// $matches[1] = keyword (for template)
		// $matches[2] = content

		// real writing is in action.php on WRITE_UPDATE_TAIL
		
		// here, use keyword or special col name '_template' to render the data structure
		if(!$this->templatePlugin) return;
		
		$keyword = trim($matches[1]);
		
		$lines = preg_split( '/\r\n|\r|\n/', $matches[2]);
		
		// need to parse the content based on "col : val" duples
		// col can have _postfix e.g. _dt (datetime), _page (wikipage), _tag (for datatag output) so on.. see dokuwiki plugin page
		//		col with 's' ending, can have multiple vals delimited with ','
		// content can have # comment

		// template name
		$template = $keyword;
		
		// parameters in template will be replaced
		$options = array();
		foreach($lines as $line) {
			if(!$line) continue;
			
			// key(or col) and value..  max 2 parts, so value can have ':' in it.
			$kv = array_map('trim', explode(':', $line, 2));

			// if _template is given, use _template instead of keyword
			if(preg_match('/^_template/', $kv[0]) && $kv[1]) {
				$template = $kv[1];
			}
			
			// col: keep postfix but remove 's', prohibited: 'class''fullpath''docname', any cols starting with '_'
			if(preg_match('/^_/', $kv[0])) continue;
			if($kv[0] == "class" || $kv[0] == "fullpath" || $kv[0] == "docname" ) continue;
			
			// check if col has 's' postfix
			preg_match('/^(.*?)(s?)$/', $kv[0], $kmatch);		// so if want to use col ending 's', append '_' to avoid truncate the last 's'
			$col = $kmatch[1];
			$plural = $kmatch[2];
		
			// comment out
			preg_match('/([^#]*)#?(.*?)$/', $kv[1], $vmatch);
			$val = $vmatch[1];
			$comment = $vmatch[2];

			// for now comment and plural values are just ignored
			
			array_push($options, $col."=".$val);
		}
		
		$ret = "{{template=".wiki_doc($this->template_ns, $template)."?".implode("&",$options)."}}";

		return $ret;
	}

	
	/**
	*
	* inline 형태의 입력: dataentry
	*
	* @param array $matches 패턴매칭 결과
	* @param array $params {@link NarinParser} 에서 전달하는 파라미터
	* @return string output
	*/
	public function wiki_dataentry_inline($matches, $params) {
		// matches[1] : keyword (or class)
		// matches[2] : list of parameter=value after '?'
	
		// real writing is in action.php on WRITE_UPDATE_TAIL
		
		// here, use keyword or special col name '_template' to render the data structure
		if(!$this->templatePlugin) return;
		
		$keyword = trim($matches[1]);

		$list = explode("&", str_replace("&amp;", "&", $matches[2]));
		
		// template name
		$template = $keyword;
		
		// parameters in template will be replaced
		$options = array();
		foreach($list as $el) {
			
			// key(or col) and value..
			$kv = array_map('trim', explode('=', $el, 2));
			
			// if _template is given, use _template instead of keyword
			if(preg_match('/^_template/', $kv[0]) && $kv[1]) {
				$template = $kv[1];
			}
				
			// col: keep postfix but remove 's', prohibited: 'class''fullpath''docname', any cols starting with '_'
			if(preg_match('/^_/', $kv[0])) continue;
			if($kv[0] == "class" || $kv[0] == "fullpath" || $kv[0] == "docname" ) continue;
				
			// check if col has 's' postfix
			preg_match('/^(.*?)(s?)$/', $kv[0], $kmatch);		// so if want to use col ending 's', append '_' to avoid truncate the last 's'
			$col = $kmatch[1];
			$plural = $kmatch[2];
			
			$val = $kv[1];
			
			// for now comment and plural values are just ignored
				
			array_push($options, $col."=".$val);
		}
		
		// template parsing
		$wikiArticle = wiki_class_load("Article");
		$t = $wikiArticle->getArticle($this->template_ns, $template);	// no need to use tempate page for create empty write object.. but y not
		$t[wr_content] = "{{template=".wiki_doc($this->template_ns, $template)."?".implode("&",$options)."}}";
		$wikiParser = new NarinParser();
		$content = $wikiParser->parse($t);
		// some post parsing..
		$pattern = '/^<div class=\'narin_contents\'>|<div id=\'wiki_toc\'>.*<!--\/\/ wiki_toc -->|<a name[^<]*><\/a>|<\/div>$/s';
		$content = preg_replace($pattern, "", $content);
		
		return $content;
	}
		
	
	/**
	 *
	 * inline 형태의 출력: datacount / datalist
	 *
	 * @param array $matches 패턴매칭 결과
	 * @param array $params {@link NarinParser} 에서 전달하는 파라미터
	 * @return string output
	 */
	public function wiki_dataout_inline($matches, $params) {
		// matches[1] : method (count or list)
		// matches[2] : '=' if any
		// matches[3] : anything b/w '=' and '?'  e.g.  도시:^인구&name=부산
		// matches[5] : list of parameter=value after '?'

		$method = $matches[1];
		
		// keyword
		$filter_keyword = "1";
		$keyword = "";
		
		// target filtering
		$filter_target = "1";
		$having_target = "";
		$filters_target = array();		// filters for target.. only for rank method
		
		// condition filtering
		$filters = array();
		
		// for datalist
		$field = 'fullpath';
		
		// for datarank
		$sort = 'ASC';
		
		// process $matches[2] if exists
		if($matches[2]) {
			$list = explode("&", str_replace("&amp;", "&", $matches[3]));
			$targets = explode(":", array_shift($list));		// first one should be keyword (count) or keyword:field or field (list,item,rank)
			if($method=="count") {
				$keyword = $targets[0];
			}else {
				if(count($targets)==1) {
					$field = $targets[0];
				}else {
					$keyword = $targets[0];
					$field   = $targets[1];
					if($method == "rank" && preg_match('/^\^(.*)$/', $field, $sort_match)) {
						$sort = 'DESC';
						$field = $sort_match[1];
					}
				}
			}
			if($keyword) $filter_keyword = " keyword = '".$keyword."' ";
				
			//if($method != "rank") break;
			
			foreach($list as $el) {
				$op_pattern = '/^(.*?)(=|\!=|&lt;&gt;|&lt;|&lt;=|=&gt;|&gt;|\~|\!\~)(.*?)$/s';
				preg_match($op_pattern, $el, $op_matches);
				$col = $op_matches[1];
				$val = $op_matches[3];
				$op  = $op_matches[2];
				$not = "";
				
				if($op=='~') {
					// wildcard
					$op = 'LIKE';
					$val = preg_replace('/\*/', '%', $val);
				}elseif($op=='!~') {
					// wildcard
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
				else array_push($filters_target, "(col = '".$col."' AND ".$not." val ".$op." '".$val."')");
			}
			
			// target filtering
			if(count($filters_target)) {
				$filter_target = implode(' OR ', $filters_target);
				$having_target = "HAVING COUNT(1)=".count($filters_target);
			}
		}
		
		
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
					if($method == "list" || $method == "item" || $method == "rank") {
						$kv = explode(":", $col);
						$val = $kv[0];
						if($kv[1]) $field = $kv[1];
						if($method == "rank" && preg_match('/^\^(.*)$/', $field, $sort_match)) {
							$sort = 'DESC';
							$field = $sort_match[1];
						}
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
		$filter_where = "1";	$having = "";
		if(count($filters)) {
			$filter_where = implode(' OR ', $filters);
			$having = "HAVING COUNT(1)=".count($filters);
		}
		
		if($method == "rank") {
			$sql_wr_id = "SELECT wr_id FROM ".$this->db_table."
								WHERE bo_table='".$this->bo_table."' AND ".$filter_keyword." AND (".$filter_target.") 
								GROUP BY wr_id ".$having_target;
			$res_wr_id = sql_query($sql_wr_id);
			$row_wr_id = mysql_fetch_assoc($res_wr_id);
			$sort_wr_id = $row_wr_id['wr_id'];
			if(!$sort_wr_id) return " <span style='color:red;'>등록정보없음</span> ";
			
			// make all wr_id list without filter_target
			mysql_query("SET @rank=0");
			$sql_rank = "SELECT rank FROM (
							SELECT @rank:=@rank+1 AS rank, wr_id, val FROM (
								SELECT d.wr_id, val FROM (
									SELECT wr_id FROM ".$this->db_table."
										WHERE bo_table='".$this->bo_table."' AND ".$filter_keyword." AND (".$filter_where.")
										GROUP BY wr_id ".$having."
									) c, ".$this->db_table." d
									WHERE d.bo_table='".$this->bo_table."' AND ".$filter_keyword." AND d.wr_id=c.wr_id AND col = '".$field."'
									ORDER BY CAST(val AS DECIMAL) ".$sort."
								) r
							) g	
							WHERE g.wr_id = ".$sort_wr_id;
					//TODO: order should be done by proper casting.. maybe need to specify the data type 
					//		e.g. _int  --> CAST(val AS UNSIGNED), _dt --> CAST(val AS DATETIME), _num  --> CAST(val AS DECIMAL)
			$res_rank = sql_query($sql_rank);
			$row_rank = mysql_fetch_assoc($res_rank);
			return " ".$row_rank['rank']." ";
		}
		
		$sql_wr_id = "SELECT wr_id FROM ".$this->db_table."
								WHERE bo_table='".$this->bo_table."' AND ".$filter_keyword." AND (".$filter_where.") 
								GROUP BY wr_id ".$having;
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
		$filter_where = "1"; $having = "";
		if(count($filters)) {
			$filter_where = implode(' OR ', $filters);		// FOR counting the satisfied wr_ids
			$having = "HAVING COUNT(1)=".count($filters);
		}
				
		// currently dataentry without keyword might have a problem to show
		$sql_wr_id = "SELECT wr_id FROM ".$this->db_table." 
						WHERE bo_table='".$this->bo_table."' AND ".$filter_keyword." AND (".$filter_where.") 
						GROUP BY wr_id ".$having;
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