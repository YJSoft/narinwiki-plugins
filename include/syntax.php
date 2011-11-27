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
	 * @format {{page=/home/nocache?box=no}}
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
		
		if($matches[4]) parse_str(str_replace("&amp;", "&", $matches[4]));	// not used yet
		
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
		
		// page access level check
		$wikiArticle = wiki_class_load("Article");
		$d = $wikiArticle->getArticle($loc, $docname);
		if($this->member[mb_level] < $d[access_level]) return "";

		if($box === "no") {		// parameter box
			$prefix = "";
			$postfix = "";
		}else {
			$prefix = "<div style='border:1px gray dotted; padding:5px;'>"
					."<div style='padding:5px 10px;background-color:#f8f8f8;'>Include된 문서: "
		            .$matches[1]."</div>";
			$postfix = "</div>";
		}
		
		// cannot include itself
		if($this->doc == $path) {
			if($box === "no") return "";		// parameter box
			else return $prefix."<div style='color:red;padding:5px;'>자기자신은 include 할 수 없습니다.</div>".$postfix;
		}

		// get current cache to avoid infinite loop parsing..
		$wikiCache = wiki_class_load("Cache");
		$content = $wikiCache->get($d['wr_id']);
		$nocache = preg_match("/~~NOCACHE~~/", $d['wr_content']);
		if(!$content || $nocache) {
			if($box === "no") return "";		// parameter box
			else return $prefix."<div style='color:red;padding:5px;'>include 대상 문서가 cache되지 않았습니다. cache된 문서만 include가 가능합니다.</div>".$postfix;
		}
		
		// only capture the specific section using $secname
		if($secname) {
			$pattern = '/<h(\d)>'.$secname.'<\/h\1>(.*?)<!--\/\/ section \1 -->/s';
			preg_match($pattern, $content, $matches);
			$content = $matches[0];
		}
		
		// remove narin_contents (out most div), wiki_toc and <a name=..></a> tag
		else {
			$pattern = '/^<div class=\'narin_contents\'>|<div id=\'wiki_toc\'>.*<!--\/\/ wiki_toc -->|<a name[^<]*><\/a>|<\/div>$/s';
			$content = preg_replace($pattern, "", $content);
		}

		return $prefix.$content.$postfix;
	}
}

?>