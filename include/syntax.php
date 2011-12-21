<?php
/**
 * 나린위키 Include 플러그인 : 플러그인 문법 클래스
 *
 * @package	   narinwiki
 * @subpackage plugin
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Jin Jun (jinjunkr@gmail.com)
 */
 
class NarinSyntaxInclude extends NarinSyntaxPlugin {

	/**
	 * 
	 * 설정된 include 허용 레벨
	 * @var integer
	 */
	var $allow_level;

	/**
	 * 
	 * 작성자 레벨
	 * @var integer
	 */
	var $writer_level = -1;

	/**
	 * 
	 * 설정된 nocontainer 허용여부
	 * @var true/false
	 */
	var $setting_nocontainer;

	/**
	 * 
	 * 설정값과 사용자 선택값에 의해 결정된 최종 nocontainer 값
	 * @var true/false
	 */
	var $nocontainer;

	/**
	 * 
	 * 설정된 include 범위
	 * @var {"전체문서와 댓글", "전체문서", "첫문단만"}
	 */
	var $setting_range;
	
	/**
	 * 
	 * 사용자 선택된 include 범위 값
	 * @var {"E", "W", "FS"}
	 * 설정과 실제 값
	 *  "전체문서와 댓글" => "E",	everything -_-
	 *  "전체문서" => "W",	whole page
	 *  "첫문단만" => "FS",	first section only
	 */
	var $include_range = "W";	// default
	
	/**
	 * 
	 * 첫문단만 include 사용자 옵션
	 * @var true/false
	 */
	var $firstseconly;
	
	/**
	 * 
	 * include 되는 문서의 최초 section level
	 * @var integer
	 */
	var $includeTopSectionLevel;
	
	/**
	 * 
	 * include 시작 이전의 기본 파서의 section 정보를 백업
	 * @var array
	 */
	var $prevSections;
	
	/**
	 * 
	 * include 시작 이전의 기본 파서의 section_level 정보를 백업
	 * @var integer
	 */
	var $prevSectionLevel;
	

	/**
	 * 
	 * include되는 문서에 의해서 복귀된 레벨
	 * @var int
	 */
	var $outdentation;
	
	/**
	 * 
	 * 파싱 시작되기 전에 변수 초기화
	 */
	function init()
	{
		$this->writer_level = -1;
		$this->includeTopSectionLevel = 999;
		$this->outdentation = 0;
	}

	/**
	 * 
	 * @see lib/NarinSyntaxPlugin::register()
	 */	
	function register($parser)
	{
		// 설정 환경
		$setting = $this->plugin_info->getPluginSetting();
		$this->allow_level = $setting[allow_level][value];
		$this->setting_nocontainer = $setting[setting_nocontainer][value];

		// 설정된 include 범위
		$this->setting_range = $setting[setting_range][value];
		if($this->setting_range === "전체문서와 댓글") $this->include_range = "E";
		elseif($this->setting_range === "전체문서") $this->include_range = "W";
		elseif($this->setting_range === "첫문단만") $this->include_range = "FS";
		
		// 파서 등록
		$parser->addVariableParser(
			$id = $this->plugin_info->getId()."_wiki_include", 
			$klass = $this, 
			$start_regx = 'page=',
			$end_regx = '((\?)(.*?))?',
			$method = "wiki_include");
	}

	/**
	 * 
	 * include 처리
	 * 
	 * @param array $matches 패턴매칭 결과
	 * @param array $params {@link NarinParser} 에서 전달하는 파라미터
	 * @return string include되고 파싱된 결과
	 */	
	public function wiki_include($matches, $params) 
	{
		// matches[1] : (/folder)/article(#section)
		// matches[4] : parameters after '?'
		
		$pattern = '/((.*)\/)([^\/\#]*)(\#(.*))?$/';
		preg_match($pattern, $matches[1], $m);
		// m[1]: root namespace if m[2]=NULL
		// m[2]: non-root namespace
		// m[3]: docname
		// m[5]: secname if any
		$loc = $m[1];
		if($m[2]) $loc = $m[2];
		$docname = $m[3];
		if($m[5]) $secname = $m[5];
//		$path = $m[1].$m[3];
		$path = wiki_doc($loc, $docname);		// better way
		
		// parse options
		if($matches[4]) parse_str(str_replace("&amp;", "&", $matches[4]));
		
		// plugin settings and alternative flags
		$this->nocontainer = false;		// init
		if($this->setting_nocontainer && ($box === "no" || isset($nocontainer)) ) $this->nocontainer = true;
		if(isset($firstseconly) || isset($fso)) $this->firstseconly = true;
		
		// 작성자 레벨 셋팅
		if($this->writer_level < 0) {
			if($params[view][mb_id]) {
				$writer = get_member($params[view][mb_id]);
				$this->writer_level = $writer[mb_level];
			} else $this->writer_level = 0;
		}
		
		// Include 사용 level check
		if($this->allow_level > $this->writer_level) return "";
		
		// folder access level check
		$wikiNS = wiki_class_load("Namespace");
		$n = $wikiNS->get($loc);
		if($this->member[mb_level] < $n[ns_access_level]) return "";
		
		// container pre/post-fix vars
		if($this->nocontainer) {		// flag box=no OR nocontainer
			$prefix = "";
			$postfix = "";
		}else {
			$prefix = "<div style='border:1px gray dotted; padding:5px; overflow:auto;'>"
					."<div style='padding:5px 10px;background-color:#f8f8f8;'>Include된 문서: "
		            .$matches[1]."</div>";
			$postfix = "</div>";
		}
		
		// get include article
		$wikiArticle = wiki_class_load("Article");
		if(!$wikiArticle->exists($loc, $docname)) {
			return $this->error_msg("nonexist", $prefix, $postfix);
		}
		$d = $wikiArticle->getArticle($loc, $docname);
		
		// page access level check
		if($this->member[mb_level] < $d[access_level]) return "";
		
		// cannot include itself
		$thisDoc = $this->doc.$this->board[bo_subject];	//TODO: should be better way..
		if($this->doc === $path || $thisDoc === $path) {
			return $this->error_msg("self", $prefix, $postfix);
		}
		
		// find the first secname and its level
		$pattern = '/[^=](=+)\s*(.*?)\s*\1[^=]/s';
		if(preg_match($pattern, $d['wr_content'], $matches)) {
			$this->includeTopSectionLevel = strlen($matches[1]);
				
			// update the secmane based on plugin setting=FS or param firstseconly
			if($this->include_range === "FS" || $this->firstseconly) {
				$secname = $matches[2];
			}
		}		
		
		// random=element 처리
		if(isset($random)) {
			// element can be section_# for section, wiki_table, wiki_code, wiki_box
			if($random === "wiki_box") {
				$matches = array();
				$lines = explode("\n", $d['wr_content']);
				$pattern = '/^\s{2,}(.*?)$/';
				foreach ($lines as $k=>$line)
				{
					if(preg_match($pattern, $line, $match) && strlen($match[1])>0 ) {
						array_push($matches, $match);
					}
				}
				
				$n = count($matches);
				if($n==0) return $prefix."해당 element가 없습니다.".$postfix;
				$rndKey = array_rand($matches);

				$d['wr_content'] = $matches[$rndKey][0];
				$wikiParser = new NarinParser();
				$content = $wikiParser->parse($d);
				
				$content = $this->treat_footnotes(&$params, $content);
				$pattern = '/^<div class=\'narin_contents\'>|<div id="wiki_footnotes">.*<\/div>$|<\/div>$/s';
				$content = preg_replace($pattern, "", $content);
				
				return $prefix.$content.$postfix;
			}
		}
		
		// extract specific section with given secname
		if($secname) {
			$pattern = '/[^=](=+)\s*'.$secname.'\s*\1[^=]/s';
			if(preg_match($pattern, $d['wr_content'],$match)) {
				if(preg_match('/[^=](=+)\s*'.$secname.'\s*\1(.*?)[^=]\1[^=]/s', $d['wr_content'], $matches)) {
					$section = $matches[1].$secname.$matches[1].$matches[2];
				}else {
					preg_match('/[^=](=+)\s*'.$secname.'.*/s', $d[wr_content], $matches);
					$section = $matches[0];
				}
				$d['wr_content'] = $section;
			}
			else {
				return $this->error_msg("nosec", $prefix, $postfix);
			}
		}
		
		// include loop check
		if($this->check_loop($d)) {
			return $this->error_msg("loop", $prefix, $postfix);
		}
		// for now ignore any 'include' in $d
		$loop_include_msg = "";
		if(!$this->nocontainer) {
			$loop_include_msg = "<html><div style='border:1px gray dotted; padding:5px; overflow:auto;'><div style='padding:5px 10px;background-color:#f8f8f8;'>Include 대상 문서: \${1}</div><div style='color:red;padding:5px;'>다단계 include 대상이나, include 하지 않았습니다.</div></div></html>";
		}
		$d['wr_content'] = preg_replace("/{{page=([^?]*)[^}]*}}/s", $loop_include_msg, $d['wr_content']);
		
		// default parser
		$plugins = &$params['plugins'];
		$default = &$plugins[array_search('wiki_default_parser', $plugins)];
		
		// parse the included page
//		$wikiParser = wiki_class_load("Parser");	// some problem...
		$wikiParser = new NarinParser();
		$content = $wikiParser->parse($d);
		
		// if nocontainer, prepare seamless including, like closing tags, section & prepare afterwards
		if($this->nocontainer) {
			$params['parser']->stop = true;
			$this->save_section(&$params);
			$closeTags = $this->get_before(&$params);
			$openTags  = $this->get_after(&$params);
		}
		
		// get rid of wiki_content, wiki_toc and wiki_footnote
		if($this->nocontainer) {
			// update footnotes
			$content = $this->treat_footnotes(&$params, $content);				
			$pattern = '/^<div class=\'narin_contents\'>|<div id=\'wiki_toc\'>.*<!--\/\/ wiki_toc -->|<div id="wiki_footnotes">.*<\/div>$|<\/div>$/s';
		}
		else {
			$pattern = '/^<div class=\'narin_contents\'>|<div id=\'wiki_toc\'>.*<!--\/\/ wiki_toc -->|<a name[^<]*><\/a>|<a href="#footnote_(\d)" name="footnote_top_(\d)" id="footnote_top_(\d)">(\d)\)|<div id="wiki_footnotes">.*<\/div>$|<\/div>$/s';
		}
		$content = preg_replace($pattern, "", $content);
		

		if($this->nocontainer) {
			// add secname to TOC
			$pattern = '/<h(\d)>([^<\/h\1]*)<\/h\1>/';
			preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
			foreach ($matches as $v) {
				array_push($default->toc, array("level"=>$v[1], "title"=>$v[2]));
			}
			
			$this->recover_section(&$params);			
			return $closeTags.$content."<!--// wiki_include -->".$openTags;
		}
		else {
			return $prefix.$content.$postfix."<!--// wiki_include -->";
		}

	}

	/**
	 *
	 * include 모드에 들어오기전의 section 과 section_level 저장
	 *
	 * @param array $params {@link NarinParser} 에서 전달하는 파라미터
	 */
	protected function save_section($params) {
		$plugins = &$params['plugins'];
		$default = &$plugins[array_search('wiki_default_parser', $plugins)];
		$this->prevSections = $default->sections;
		$this->prevSectionLevel = $default->section_level;
	}

	/**
	 *
	 * include 모드에 들어오기전의 section 과 section_level 복원
	 *
	 * @param array $params {@link NarinParser} 에서 전달하는 파라미터
	 */
	public function recover_section($params)
	{
		if(!$this->nocontainer) return;
		
		$plugins = &$params['plugins'];
		$default = &$plugins[array_search('wiki_default_parser', $plugins)];
		$default->sections = $this->prevSections;
		$default->section_level = $this->prevSectionLevel;
	}
	
	/**
	 *
	 * 기본 문법 해석기의 열린 태그 닫음
	 *   - section, table, p, ul, ol 등의 태그가 열려있으면 닫아줌
	 *
	 * @param array $params {@link NarinParser} 에서 전달하는 파라미터
	 * @return string 닫는 태그
	 */
	public function get_before($params) {
		$plugins = &$params['plugins'];
		$default = &$plugins[array_search('wiki_default_parser', $plugins)];
	
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
		
		for($i=count($default->sections)-1; $i>=0; $i--) {
			$pSection = $default->sections[$i];
			if($pSection['level'] >= $this->includeTopSectionLevel) {
				$this->outdentation++;
				$close_tag .= $pSection['close_tag'];
				array_pop($default->sections);
			}
		}

		return $close_tag;
	}
	
	
	/**
	*
	* 기본 문법 해석기의 include 전에 닫은 section 태그 다시 열어줌
	*
	* @param array $params {@link NarinParser} 에서 전달하는 파라미터
	* @return string 닫는 태그
	*/
	public function get_after($params) {
		$plugins = &$params['plugins'];
		$default = &$plugins[array_search('wiki_default_parser', $plugins)];
	
		$open_tag = '';
		for($i=$this->includeTopSectionLevel, $j=0; $i<=$default->section_level && $j<$this->outdentation; $i++, $j++) {
			$open_tag .= "<div class='wiki_section wiki_section_$i'>";
		}
	
		return $open_tag;
	}
	
	/**
	 *
	 * include 루프 검사
	 *
	 * @param {@link Article} class
	 * @return true/false
	 */
	protected function check_loop($wiki)
	{
		// for now, just ignore any "include" in included page
		return false;
		//TODO: need to check include loop
	}
	
	
	/**
	 * update and insert footnotes in included page
	 * 
	 * @param array $params {@link NarinParser} 에서 전달하는 파라미터
	 * @param string parsed wiki content
	 * @return string updated wiki content
	 */
	protected function treat_footnotes($params, $content) {
		$plugins = &$params['plugins'];
		$default = &$plugins[array_search('wiki_default_parser', $plugins)];

		// find footnotes in included page
		$patternFootnote = '/name="footnote_(\d)">\1\)<\/a><\/sup>([^<]*)</s';
		preg_match_all($patternFootnote, $content, $matches, PREG_SET_ORDER);
		foreach ($matches as $idx=>$v) {
			$fIdx = trim($v[1]);
			$fVal = trim($v[2]);
			
			array_push($default->footnotes, $fVal);
		}
		$nIdx = count($default->footnotes);

		// replace footnote index reverse order
		foreach(array_reverse($matches, true) as $idx=>$v) {
			$fIdx = trim($v[1]);
			$fVal = trim($v[2]);
			
			$content = preg_replace( '/<a href="#footnote_'.$fIdx.'" name="footnote_top_'.$fIdx.'" id="footnote_top_'.$fIdx.'">'.$fIdx.'\)/s',
				'<a href="#footnote_'.$nIdx.'" name="footnote_top_'.$nIdx.'" id="footnote_top_'.$nIdx.'">'.$nIdx.')', $content);
			$nIdx--;
		}
		return $content;
	}
	
	
	
	/**
	 *
	 * include 오류 출력
	 *
	 * @param string $type 오류종류
	 * @param string $prefix prefix
	 * @param string $postfix postfix
	 * @return string 오류문장
	 */
	protected function error_msg($type, $prefix, $postfix)
	{
		if($this->nocontainer) return "";
		else {
			$msg = "문제가 발생했습니다.";
			if($type === "nonexist") $msg = "없는 문서입니다.";
			elseif ($type === "self") $msg = "자기자신은 include 할 수 없습니다.";
			elseif ($type === "loop") $msg = "include 반복(loop)이 존재하여 표시할 수 없습니다.";
			elseif ($type === "nocache") $msg = "include 대상 문서가 cache되지 않았습니다. cache된 문서만 include가 가능합니다.";
			elseif ($type === "nosec") $msg = "include 대상 section이 없습니다.";
				
			return $prefix."<div style='color:red;padding:5px;'>".$msg."</div>".$postfix;
		}
	}
}

?>