<?php
/**
 * 나린위키 Template 플러그인 : 플러그인 정보 클래스
 *
 * 사용법: 	{{template=/틀/상자?제목=상자제목&내용=상자 내용}}	
 * 					- /틀/상자 틀에 정의된 @제목@ 과 @내용@ 을 주어진 파라미터로 채우고, 
 * 					  /틀/상자 에서 정의된 방법으로 표현한후 문서에 포함 (예, float:right 박스)
 * 
 * @package	   narinwiki
 * @subpackage plugin
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Jin Jun (jinjunkr@gmail.com)
 */
 
class NarinPluginInfoTemplate extends NarinPluginInfo {

	/**
	 * 생성자
	 */		  	
	public function __construct() {
		$this->id = "wiki_template";		
		parent::__construct();
		$this->init();
	}	  	

	/**
	 * 
	 * 플러그인 설명
	 * 
	 * @return string 플러그인 설명
	 */
	public function description()
		{
		return "Template 플러그인 (저자 : Jin Jun, jinjunkr@gmail.com)";
	}
		
	/**
	 *
	 * @see lib/NarinPluginInfo::getSetting()
	 */
	public function getSetting() {
		return array(
			"allow_level"=>array("type"=>"select", "label"=>"플러그인 사용 권한", "desc"=>"설정된 권한보다 낮은 레벨의 사용자가 작성한 문서의 template는 화면에 그대로 출력됩니다.", "options"=>array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10), "value"=>2)
		);		
	}
}



?>