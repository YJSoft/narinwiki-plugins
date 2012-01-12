<?php
/**
 * 나린위키 Template 플러그인 : 플러그인 정보 클래스
 *
 * @package	   narinwiki
 * @subpackage plugin
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Jin Jun (jinjunkr@gmail.com)
 */

//TODO: 템플릿을 보다 구조적으로 간편하게 만들수 있어야..
//		- css와 템플릿을 class로 상속받을 수 있게
//		- css는 .. 아마 여기서 대표적인 경우를 몇개 만들어야..
class NarinSyntaxTemplate extends NarinSyntaxPlugin {

	/**
	 * 
	 * 플러긴 사용허용 레벨
	 * @var integer
	 */
	var $allow_level;
	
	/**
	 *
	 * template 블럭을 저장할 버퍼: array("position","content")
	 * @var array
	 */
	var $blocks = array();
	
	/**
	 * 파싱 시작되기 전에 변수 초기화
	 */
	function init()
	{
//		$this->writer_level = -1;
	}

	/**
	 *
	 * @see lib/NarinSyntaxPlugin::register()
	 */
	function register($parser)
	{
		$setting = $this->plugin_info->getPluginSetting();		
		$this->allow_level = $setting[allow_level][value];

		$parser->addVariableParser(
			$id = $this->plugin_info->getId()."_wiki_expr", 
			$klass = $this, 
			$start_regx = 'expr=',
			$end_regx = '((\?)(.*?))?',
			$method = "wiki_expr");
		
		$parser->addVariableParser(
			$id = $this->plugin_info->getId()."_wiki_template", 
			$klass = $this, 
			$start_regx = 'template=',
			$end_regx = '((\?)(.*?))?',
			$method = "wiki_template");
		

		// some tags to control the scope of templating
		//										between tags				the rest of page
		//									direct		templated		direct		templated
		// <includeonly>...</includeonly> 	  No			Yes			  -				-
		// <noinclude>...</noinclude>		  Yes			No			  -				-
		// <onlyinclude>...</onlyinclude>	  Yes			Yes			  Yes			No
		$block_regs = array(
			"includeonly"=>array("tag"=>"includeonly", "method"=>"ignoretags"),
			"noinclude"=>array("tag"=>"noinclude", "method"=>"firstmatch"),
			"onlyinclude"=>array("tag"=>"onlyinclude", "method"=>"firstmatch"),
		);
		
		foreach($block_regs as $func => $v) {
			$parser->addBlockParser(
				$id = $this->plugin_info->getId()."_".$func,
				$klass = $this,
				$startRegx = "&lt;".$v["tag"]."&gt;",
				$endRegx = "&lt;\/".$v["tag"]."&gt;",
				$method = $v["method"]);
		}

		// some formats only valid when templated
		// <<..##..@@..@@..##..>>	: keep ##..@@..@@..## to show the pattern, but not the outmost one
		// poisitonal control tags
		$word_regs = array(
			"fieldSet"=>array("regx"=>"&lt;&lt;(.*?)&gt;&gt;", "method"=>"firstmatch"),
			"closeall"=>array("regx"=>"~~CLOSEALL~~", "method"=>"ignoretags"),
			"pagetop"=>array("regx"=>"~~PAGETOP~~", "method"=>"ignoretags"),
			"pagebottom"=>array("regx"=>"~~PAGEBOTTOM~~", "method"=>"ignoretags")
		);
		
		foreach($word_regs as $func => $v) {
			$parser->addWordParser(
				$id = $this->plugin_info->getId()."_".$func,
				$klass = $this,
				$regx = $v["regx"],
				$method = $v["method"]);
		}
		
		// position the template
		// positional control tags are disabled in 2012-01-11 vesrion
		//$parser->addEvent(EVENT_AFTER_PARSING_ALL, $this, "wiki_restore_template");
	}
	
	
	public function wiki_expr($matches, $params)
	{
		$expr = $matches[1];
		//TODO: better way to validate $expr
		if(preg_match('/@@/', $expr, $m)) return $expr;	// no eval for template itself
		
		// parse options (only format=)
		if($matches[4]) parse_str(str_replace("&amp;", "&", $matches[4]));
		
		$expr = preg_replace('/,(\d{3})/', '\1', $expr);
		//TODO: no way to distinguish b/w power(2,100) and 2,100
		//	probably need to make numbers before expr
		eval("\$return = ".$expr.";");

		$n_frac = 2;
		$d_sep = '.';
		$t_sep = '';
		$percent = "";
		if($format) {
			preg_match('/(,)?(\d*)?(\.(\d*))?(s|d|u|f|\%)/', $format, $matches);

			if(preg_match('/,/s', $matches[1])) {
				$t_sep = ',';
			}
			
			if(isset($matches[4])) $n_frac = $matches[4];
			
			// currently only '%' affects the format
			if($matches[5]=='%') {
				$return = $return * 100;
				$percent = '%';
			}
		}
		return number_format($return, $n_frac, $d_sep, $t_sep).$percent;
	}

	/**
	 * 
	 * template 처리
	 * 
	 * @param array $matches 패턴매칭 결과
	 * @param array $params {@link NarinParser} 에서 전달하는 파라미터
	 * @return
	 */	
	public function wiki_template($matches, $params) 
	{
		// matches[1] : /template/form
		// matches[4] : list of parameter=value after '?'
				
		$pattern = '/((.*)\/)([^\/\#]*)(\#(.*))?$/';
		preg_match($pattern, $matches[1], $m);
		
		// m[1]: root namespace if m[2]=NULL
		// m[2]: non-root namespace
		// m[3]: docname
		// m[5]: secname if any
		$args = array();
		$args['loc'] = $m[2] ? $m[2] : $m[1];
		$args['docname'] = $m[3];
		$args['secname'] = $m[5] ? $m[5] : "";
		$args['path'] = wiki_doc($args['loc'], $args['docname']);
//		$args['options'] = htmlspecialchars($matches[4]);
		$args['options'] = urlencode($matches[4]);
		
		// 작성자 레벨 셋팅
		if($params[view][mb_id]) {
			$writer = get_member($params[view][mb_id]);
			$args['writer_level'] = $writer[mb_level];
		} else $args['writer_level'] = 0;
		
		$templated = $this->wiki_template_nojs(&$args, &$params);
		
		$options = wiki_json_encode($args);
		
		return '<nocache plugin="template" method="cache_render" params="'.addslashes($options).'">'.$templated.'</nocache>';
	}

	/**
	 *
	 * 부분 캐시 랜더 함수
	 *
	 * @param array $args {@link narin.php} 에서 전달하는 파라미터
	 * @return string HTML 태그
	 */
	public function cache_render($args) {
		return $this->wiki_template_nojs($args, null);
	}
	
	/**
	 *
	 * template 처리 without js (currently no js version though)
	 *
	 * @param array $args 파라미터
	 * @param array $params {@link NarinParser} 에서 전달하는 파라미터/ can be null
	 * @return string include되고 파싱된 결과
	 */
	public function wiki_template_nojs($args, $params) {
		// default parser, if params is not null
		if($params) {
			$wikiParser = new NarinParser();
				
			$plugins = &$params['plugins'];
			$default = &$plugins[array_search('wiki_default_parser', $plugins)];
		}else {
			$wikiParser = wiki_class_load("Parser");
		}
			
		$parameters = array();
		$values = array();
		if($args['options']) {
//			$list = explode("&", str_replace("&amp;", "&", htmlspecialchars_decode($args['options']) ));
			$list = explode("&", str_replace("&amp;", "&", urldecode($args['options']) ));
			foreach($list as $el) {
				$pair = explode("=", $el);
				array_push($parameters, "/@@".$pair[0]."@@/");
				array_push($values, $pair[1]);
			}
		}	
		// Template 사용 level check
		if($this->allow_level > $args['writer_level']) return "";
		
		// folder access level check
		$wikiNS = wiki_class_load("Namespace");
		$n = $wikiNS->get($args['loc']);
		if($this->member['mb_level'] < $n['ns_access_level']) return "";
		
		// template access level check
		$wikiArticle = wiki_class_load("Article");
		if(!$wikiArticle->exists($args['loc'], $args['docname'])) return "";
		$t = $wikiArticle->getArticle($args['loc'], $args['docname']);
		if($this->member['mb_level'] < $t['access_level']) return "";

//		$prefix = "<div style='border:1px gray dotted; padding:5px;'><div style='padding:5px 10px;background-color:#f8f8f8;'>사용된 틀: "
//		            .$matches[1]."</div>";
//		$postfix = "</div>";
		$prefix = "<div class='wiki_template'>";
		$postfix = "</div>";
		
		// cannot include itself, just in case
		//if($this->doc == $path) return $prefix."<div style='color:red;'>자기자신은 include 할 수 없습니다.</div>".$postfix;

		// close all open tags
		if(preg_match('/~~CLOSEALL~~/', $t['wr_content'])) {
			if($params) {	// save the 'closing' html
				$args['closeall'] = htmlspecialchars($this->get_close(&$default));
			}
			$prefix = htmlspecialchars_decode($args['closeall']).$prefix;		// it might mess up with section level when used with ~~PAGEBOTTOME~~
			$t['wr_content'] = preg_replace('/~~CLOSEALL~~/', '', $t['wr_content']);
		}
		
		// move template 
		$template_position = "HERE";
		if(preg_match('/~~PAGETOP~~/', $t['wr_content'])) {
			$template_position = "TOP";
			$t['wr_content'] = preg_replace('/~~PAGETOP~~/', '', $t['wr_content']);
		}
		if(preg_match('/~~PAGEBOTTOM~~/', $t['wr_content'])) {
			$template_position = "BOTTOM";
			$t['wr_content'] = preg_replace('/~~PAGEBOTTOM~~/', '', $t['wr_content']);
		}
		
		
		// onlyinclude
		if(preg_match('/<onlyinclude>(.*?)<\/onlyinclude>/s', $t['wr_content'], $onlyinclude)) {
			$t['wr_content'] = $onlyinclude[1];
		}
		
		// noinclude
		$t['wr_content'] = preg_replace('/<noinclude>(.*?)<\/noinclude>/s', "", $t['wr_content']);
		
		// can we do foreach (##...@@--@@...##) and exist (<<...@@--@@...>>) ??
		// e.g.		<<카테고리: ##[[/카테고리/@@name@@]]## \\>>
		foreach($parameters as $k=>$p) {
			if(!$values[$k]) continue;
			$pattern = '/(<<([^<#@]*?))?(##([^<#@]*?))?'.trim($p,"/").'(([^<#@]*?)##)?(([^<#@]*?)>>)?/s';
			preg_match_all($pattern, $t[wr_content], $matches_all, PREG_SET_ORDER);
			foreach($matches_all as $m) {
				$old	= $m[0];
				$start	= $m[2];
				$R_open = $m[4];
				$R_end	= $m[6];
				$end	= $m[8];
				
				$val = $values[$k];
				
				// assume ', ' being delimiter
				$array = array();
				$array = explode(', ', $val);
				$new_array = array();
				foreach($array as $a) {
					array_push($new_array, $R_open.$a.$R_end);
				}
				$new = implode(', ', $new_array);
				
				$t['wr_content'] = str_replace($old, $start.$new.$end, $t['wr_content']);
			}
		}
		
		// clean rest of <<..@@..@@..>>
		// TODO: <<..{{expr=@@..@@+@@..@@}}..>> cannot be cleaned when one of the args in expr
		$pattern = '/(<<([^<#@]*?))?(##([^<@]*?))?@@[^@]*@@(([^<@]*?)##)?(([^<@]*?)>>)?/s';
		preg_match($pattern, $t['wr_content'], $matches);
		$t['wr_content'] = preg_replace($pattern, "", $t['wr_content']);
		
		// now anything without <<..>> or ##..##, althought seems unnecessary
		// replacing
		$t['wr_content'] = preg_replace($parameters, $values, $t['wr_content']);
		// delete any missing @@--@@s
		$t['wr_content'] = preg_replace("/@@[^@]*@@/","",$t['wr_content']);
		
		// parse the replaced template
		$content = $wikiParser->parse($t);
		
		// some post parsing..
		$pattern = '/^<div class=\'narin_contents\'>|<div id=\'wiki_toc\'>.*<!--\/\/ wiki_toc -->|<a name[^<]*><\/a>|<\/div>$/s';
		$content = preg_replace($pattern, "", $content);

		// currently, just return it withouth adjusting the position of templated content in 2012-01-11 version
		return $prefix.$content.$postfix;

		// try to do both, addEvent(EVENT_AFTER_PARSING_ALL, ...) and partial nocache
		if($params) {
			// for initial rendering
			array_push($this->blocks, array("position"=>$template_position, "content"=>$prefix.$content.$postfix) );
			return "<template></template>";
		}else {
			return $prefix.$content.$postfix;
		}
	}
	

	/**
	 *
	 * 코드 복구 (after parsing)
	 *
	 * @param array $params {@link NarinParser} 에서 전달하는 파라미터
	 */
	public function wiki_restore_template($params) {
		if($this->allow_level > $this->writer_level) return;
		
		preg_match_all('/<template><\/template>/i', $params['output'], $matches, PREG_SET_ORDER);
		foreach($matches as $m) {
			$block = $this->blocks[0];
			if($block['position'] == "HERE") {
				$params['output'] = preg_replace('/<template><\/template>/i', $block['content'], $params['output'], 1);
			}
			elseif($block['position'] == "TOP") {
				$params['output'] = $block['content'].preg_replace('/<template><\/template>/i', "", $params['output'], 1);
			}
			elseif($block['position'] == "BOTTOM") {
				$params['output'] = preg_replace('/<template><\/template>/i', "", $params['output'], 1).$block['content'];
			}
			array_shift($this->blocks);
		}
	}
	
	/**
	 *
	 * 기본 문법 해석기의 열린 태그 닫음 (copy from column plugin - get_close()
	 *   - section, table, p, ul, ol 등의 태그가 열려있으면 닫아줌 (~~CLOSEALL~~)
	 *
	 * @param array $default default parser
	 * @return string 닫는 태그
	 */
	protected function get_close($default) {
		$close_tag = '';
		
		if ($default->list_level>0)
		{
			$close_tag .= $default->wiki_list(false, array(), '', '', true);
		}
		if ($default->boxformat)
		{
			$close_tag .= $default->wiki_box(false, array(), true);
		}
		if ($default->pformat)
		{
			$close_tag .= $default->wiki_par(false, array(), true);
		}
		if ($default->table_opened)
		{
			$close_tag .= $default->wiki_table(false, array(), true);
		}
		while($pSection = array_pop($default->sections)) {
			$close_tag .= $pSection['close_tag'];
		}			
				
		return $close_tag;						
	}


	/**
	 *
	 * return the 1st match: <includeonly></includeonly> so on..
	 *
	 * @param array $matches 패턴매칭 결과
	 * @param array $params {@link NarinParser} 에서 전달하는 파라미터
	 * @return string 닫는 태그
	 */
	public function firstmatch($matches, $params) {
		return $matches[1];
	}
	
	/**
	 *
	 * ignore any extra tags.. only affecting when templated
	 *
	 * @param array $matches 패턴매칭 결과
	 * @param array $params {@link NarinParser} 에서 전달하는 파라미터
	 * @return string 닫는 태그
	 */
	public function ignoretags($matches, $params) {
		return "";
	}
	
}
?>