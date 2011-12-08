<?php
/**
 * 나린위키 Include 플러그인 : 플러그인 정보 클래스
 *
 * Narinwiki porting of Dokuwiki include plugin (http://www.dokuwiki.org/plugin:include)
 * 
 * 사용법: 	{{page=/home/welcome}}			- /home/welcome 문서 전체를 포함
 * 			{{page=/home/welcome#special}}  - /home/welcome의 special 섹션만 포함
 * 			{{page=/home/nocache?box=no}}	- include without box container, and print no error if any
 * 											default: box=yes
 * 
 * 계획중인 flags	-> usage: {{page=/home/wiki?box=no&nocontainer}}
 * 			nocontainer		== box=no
 * 			firstseconly	shows first seciton only
 * 			showfooter		include included page info in the footer section	== footer=yes	
 * 			noheades		strips the title from the included page
 * 			comments		include first N comments	== comments=5
 * 
 * 주의: 포함대상 문서는 cache 되어 있어야 함.
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Jin Jun (jinjunkr@gmail.com)
 */
 
class NarinPluginInfoInclude extends NarinPluginInfo {

	/**
	 * 생성자
	 */		  	
	public function __construct() {
		$this->id = "wiki_include";		
		parent::__construct();
	}	  	

	/**
	 * 플러그인 설명
	 */
	public function description()
	{
		return "Include 플러그인 (저자 : Jin Jun, jinjunkr@gmail.com)";
	}
		
	/**
	 * 플러그인 설정
	 */
	public function getSetting() {
		return array(
			"allow_level"=>array("type"=>"select", "label"=>"플러그인 사용 권한", "desc"=>"설정된 권한보다 낮은 레벨의 사용자가 작성한 문서의 include는 무시됩니다.", "options"=>array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10), "value"=>2)
		);		
	}
}



?>