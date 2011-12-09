<?php
/**
 * 나린위키 Include 플러그인 : 플러그인 정보 클래스
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Jin Jun (jinjunkr@gmail.com)
 */
 
class NarinSyntaxInclude extends NarinSyntaxPlugin {

	var $blocks = array();
	var $allow_level;
	var $writer_level = -1;
	var $setting_nocontainer;
	var $nocontainer;
	var $setting_range;
	// "전체문서와 댓글" => "E",	everything -_-
	// "전체문서" => "W",	whole page
	// "첫문단만" => "FS",	first section only
	var $include_range = "W";	// default
	var $firstseconly;
	
		  	
	/**
	 * 파싱 시작되기 전에 변수 초기화
	 */
	function init()
	{
		$this->blocks = array();
		$this->writer_level = -1;
	}

	/**
	 * 파서 등록
	 */	
	function register($parser)
	{
		$setting = $this->plugin_info->getPluginSetting();		
		$this->allow_level = $setting[allow_level][value];
		$this->setting_nocontainer = $setting[setting_nocontainer][value];
		
		$this->setting_range = $setting[setting_range][value];
		if($this->setting_range === "전체문서와 댓글") $this->include_range = "E";
		elseif($this->setting_range === "전체문서") $this->include_range = "W";
		elseif($this->setting_range === "첫문단만") $this->include_range = "FS";
		
		$parser->addVariableParser(
			$id = $this->plugin_info->getId()."_wiki_include", 
			$klass = $this, 
			$start_regx = 'page=',
			$end_regx = '((\?)(.*?))?',
			$method = "wiki_include");
	}

	/**
	 * include 처리
	 * @format {{page=/home/welcome}}
	 * @format {{page=/home/welcome#special}}
	 * @format {{page=/home/welcome?nocontainer}}
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
		$path = $m[1].$m[3];
		
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
		
		if($this->nocontainer) {		// flag box=no OR nocontainer
			$prefix = "";
			$postfix = "";
		}else {
			$prefix = "<div style='border:1px gray dotted; padding:5px; overflow:auto;'>"
					."<div style='padding:5px 10px;background-color:#f8f8f8;'>Include된 문서: "
		            .$matches[1]."</div>";
			$postfix = "</div>";
		}
		
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

		// get the current cache to avoid infinite loop parsing..
		$wikiCache = wiki_class_load("Cache");
		$content = $wikiCache->get($d['wr_id']);
		$nocache = preg_match("/~~NOCACHE~~/", $d['wr_content']);
		if(!$content || $nocache) {
			return $this->error_msg("nocache", $prefix, $postfix);
		}

		if($this->include_range === "FS" || $this->firstseconly) {
			//TODO::  replace $secname with the first sec name if any
			$pattern = '/<h(\d)>(.*?)<\/h\1>/s';
			if(preg_match($pattern, $content, $matches)) {
				$secname = $matches[2];
			}
		}
		
		// only capture the specific section using $secname
		if($secname) {
			$pattern = '/<h(\d)>'.$secname.'<\/h\1>(.*?)<!--\/\/ section \1 -->/s';		// this is dependent on narinwiki annotatin..-_-
			if(preg_match($pattern, $content, $matches)) {
				$content = $matches[0];
			} else {
				return $this->error_msg("nosec", $prefix, $postfix);
			}
		}
		// remove narin_contents (out most div), wiki_toc and <a name=..></a> tag
		else {
			$pattern = '/^<div class=\'narin_contents\'>|<div id=\'wiki_toc\'>.*<!--\/\/ wiki_toc -->|<a name[^<]*><\/a>|<\/div>$/s';
			$content = preg_replace($pattern, "", $content);
		}

		return $prefix.$content.$postfix;
	}
	
	function error_msg($type, $prefix, $postfix)
	{
		if($this->nocontainer) return "";
		else {
			$msg = "문제가 발생했습니다.";
			if($type === "nonexist") $msg = "없는 문서입니다.";
			elseif ($type === "self") $msg = "자기자신은 include 할 수 없습니다.";
			elseif ($type === "nocache") $msg = "include 대상 문서가 cache되지 않았습니다. cache된 문서만 include가 가능합니다.";
			elseif ($type === "nosec") $msg = "include 대상 section이 없습니다.";
				
			return $prefix."<div style='color:red;padding:5px;'>".$msg."</div>".$postfix;
		}
	}
}

?>