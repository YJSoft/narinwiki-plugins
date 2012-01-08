<?php
/**
 * 나린위키 Include 플러그인 : 플러그인 정보 클래스
 *
 * Narinwiki porting of Dokuwiki include plugin (http://www.dokuwiki.org/plugin:include)
 * 
 * 사용법: 	{{page=/home/welcome}}			- /home/welcome 문서 전체를 포함
 * 			{{page=/home/welcome#special}}  - /home/welcome의 special 섹션만 포함
 * 			{{page=/home/welcome?nocontainer}}	- include without a container, and print no error if any
 * 			{{page=/home/welcome?firstseconly}}	- include the first sec only if any.  this ignores the #sec option
 * 			{{page=/home/welcome?random=element}}	- include random element in /home/welcome page or /home/welcome/ folder. 
 * 											element could be section_# for section, wiki_table, wiki_code, wiki_box
 * 											e.g. random=wikibox : include random wikibox esp. designed for nextpeople.kr
 * 
 * 계획중인 flags	-> usage: {{page=/home/wiki?flag1&setting2=flag2}}
 * 			comments		include first N comments	== comments=5
 * 
 * 
 * @package	   narinwiki
 * @subpackage plugin
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Jin Jun (jinjunkr@gmail.com)
 */
 
class NarinPluginInfoInclude extends NarinPluginInfo {

	/**
	 * 
	 * 생성자
	 */		  	
	public function __construct() {
		$this->id = "wiki_include";		
		parent::__construct();
		$this->init();
	}	  	

	/**
	 * 
	 * 플러그인 설명
	 * 
	 * @return string 플러그인설명
	 */
	public function description()
	{
		return "Include 플러그인 (저자 : Jin Jun, jinjunkr@gmail.com)";
	}
		
	/**
	 * 
	 * 플러그인 설정
	 * 
	 * @return array 플러그인 설정 정보
	 */
	public function getSetting() {
		return array(
			"allow_level"=>array("type"=>"select", "label"=>"플러그인 사용 권한", "desc"=>"설정된 권한보다 낮은 레벨의 사용자가 작성한 문서의 include는 무시됩니다.", "options"=>array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10), "value"=>2)
			,"setting_nocontainer"=>array("type"=>"checkbox", "label"=>"nocontainer 허용", "desc"=>"이를 허용하지 않으면, 개별 nocontainer 옵션은 무시됩니다.", "value"=>1)
			,"setting_range"=>array("type"=>"select", "label"=>"include 허용 범위 지정", "desc"=>"설정된 범위보다 작은 범위만 허용합니다.", "options"=>array("전체문서","첫문단만"), "value"=>1)
//			,"setting_range"=>array("type"=>"select", "label"=>"include 허용 범위 지정", "desc"=>"설정된 범위보다 작은 범위만 허용합니다. (댓글포함은 아직 지원되지 않습니다)", "options"=>array("전체문서와 댓글","전체문서","첫문단만"), "value"=>2)
		);		
	}
}



?>