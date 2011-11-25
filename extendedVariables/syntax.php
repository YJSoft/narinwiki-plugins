<?php
/**
 * 나린위키의 확장된 변수를 사용하기 위한 플러그인 : 플러그인 정보 클래스
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Jin Jun (jinjunkr@gmail.com)
 */
 
class NarinSyntaxExtendedVariables extends NarinSyntaxPlugin {

	var $blocks = array();
		  	
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

		// @format: {{WIKINOW:__}} - __ can be following
		//			FULL: 	2011년 12월 13일 오후 2:15 (KST)
		//			YYMMDD:	11/12/13
		//			HHMM:	14:15		so on..
		// @format: {{FULLPAGENAME}}	- /home/welcome
		// @format: {{WIKINAME}}		- $wiki[front]
		$variable_regs = array(
			"wikinow"=>array("start_regx"=>"WIKINOW", "end_regx"=>"(:(.*))?"),
			"fullpagename"=>array("start_regx"=>"FULLPAGENAME", "end_regx"=>""),
			"wikiname"=>array("start_regx"=>"WIKINAME", "end_regx"=>"")
		);
		
		foreach($variable_regs as $func => $v) {
			$parser->addVariableParser(
				$id = $this->plugin_info->getId()."_".$func,
				$klass = $this,
				$startRegx = $v["start_regx"],
				$endRegx = $v["end_regx"],
				$method = $func);
			}
	}

	/**
	 * WIKINOW 처리
	 * @format {{WIKINOW:FULL}}
	 */	
	public function wikinow($matches, $params) 
	{
		// matches[3] : option after ':'

		$content = date("Y-m-d H:i:s");
		switch($matches[3]) {
			case 'YYMMDD':
				$content = date('y/m/d');
				break;
			case 'HHMM':
				$content = date('H:i');
				break;
			case 'FULL':
				$content = date('Y\년 n\월 j\일 A g:i  \(e\)');
				break;
		}

		return $content;
	}
	
	/**
	* FULLPAGENAME 처리
	* @format {{FULLPAGENAME}}
	*/
	public function fullpagename($matches, $params)
	{
		return $this->doc;
	}

	
	/**
	* WIKINAME 처리
	* @format {{WIKINAME}}
	*/
	public function wikiname($matches, $params)
	{
		return $this->wiki[front];
	}
	
}

?>