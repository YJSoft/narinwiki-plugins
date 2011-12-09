<?php
/**
 * 나린위키의 확장된 변수를 사용하기 위한 플러그인 : 플러그인 정보 클래스
 *
 * format: 	{{WIKINOW:FULL}}			- 2011년 11월 25일 (금) 12:30 (KST)
 * 
 * 주의사항:	현재는 사용에 레벨제한이 없습니다.
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Jin Jun (jinjunkr@gmail.com)
 */
 
class NarinPluginInfoExtendedVariables extends NarinPluginInfo {

	/**
	 * 생성자
	 */		  	
	public function __construct() {
		$this->id = "wiki_extended_variables";		
		parent::__construct();
		$this->init();
	}	  	

	/**
	 * 플러그인 설명
	 */
	public function description()
	{
		return "유용한 확장 변수 플러그인 (저자 : Jin Jun, jinjunkr@gmail.com), 예 WIKITIME:FULL -> '2011년 11월 25일 (금) 12:30 (KST)'";
	}
}



?>