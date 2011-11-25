<?php
/**
 * 나린위키 Template 플러그인 : 플러그인 정보 클래스
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Jin Jun (jinjunkr@gmail.com)
 */

//TODO: 템플릿을 보다 구조적으로 간편하게 만들수 있어야..
//		- css와 템플릿을 class로 상속받을 수 있게
//		- css는 .. 아마 여기서 대표적인 경우를 몇개 만들어야..
class NarinSyntaxTemplate extends NarinSyntaxPlugin {

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
			$id = $this->plugin_info->getId()."_wiki_template", 
			$klass = $this, 
			$start_regx = 'template=',
			$end_regx = '((\?)(.*?))?',
			$method = "wiki_template");
	}

	/**
	 * template 처리
	 * @format {{template=/template/notice}}	- without parameters
	 * @format {{template=/template/infobox?title=TITLE&text=content text}}		- values will replace @title@, @text@ in /template/infobox
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
		$loc = $m[1];
		if($m[2]) $loc = $m[2];
		$docname = $m[3];
		if($m[5]) $secname = $m[5];
		$path = $m[1].$m[3];
		
		$parameters = array();
		$values = array();
		if($matches[4]) { 
			$list = explode("&", str_replace("&amp;", "&", $matches[4]));
			foreach($list as $el) {
				$pair = explode("=", $el);
				array_push($parameters, "/@".$pair[0]."@/");
				array_push($values, $pair[1]);
			}
		}
		
		// 작성자 레벨 셋팅
		if($this->writer_level < 0) {
			if($params[view][mb_id]) {
				$writer = get_member($params[view][mb_id]);
				$this->writer_level = $writer[mb_level];
			} else $this->writer_level = 0;
		}
		
		// Template 사용 level check
		if($this->allow_level > $this->writer_level) return "";
		
		// folder access level check
		$wikiNS = wiki_class_load("Namespace");
		$n = $wikiNS->get($loc);
		if($this->member[mb_level] < $n[ns_access_level]) return "";
		
		// template access level check
		$wikiArticle = wiki_class_load("Article");
		$t = $wikiArticle->getArticle($loc, $docname);
		if($this->member[mb_level] < $t[access_level]) return "";

//		$prefix = "<div style='border:1px gray dotted; padding:5px;'><div style='padding:5px 10px;background-color:#f8f8f8;'>사용된 틀: "
//		            .$matches[1]."</div>";
//		$postfix = "</div>";
		
		// cannot include itself, just in case
		if($this->doc == $path) return $prefix."<div style='color:red;'>자기자신은 include 할 수 없습니다.</div>".$postfix;

		// replacing
		$t[wr_content] = preg_replace($parameters, $values, $t[wr_content]);
		// delete any missing @--@s
		// TODO: need to delete any associate filed, e.g. <tr><th>title</th><td>@param@</td></tr>
		$t[wr_content] = preg_replace("/@[^@]*@/","",$t[wr_content]);
		$content = $t[wr_content];

		// parse the replaced template
		$wikiParser = wiki_class_load("Parser");
		$wikiParser = new NarinParser();
		$content = $wikiParser->parse($t);
		
		// some post parsing..
		$pattern = '/^<div class=\'narin_contents\'>|<div id=\'wiki_toc\'>.*<!--\/\/ wiki_toc -->|<a name[^<]*><\/a>|<\/div>$/s';
		$content = preg_replace($pattern, "", $content);

		return $prefix.$content.$postfix;
	}
}
?>