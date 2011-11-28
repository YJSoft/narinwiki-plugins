<?
/**
 * 나린위키 sortableTable 플러그인 : 플러그인 정보 클래스
 *
 * 사용법: 	
 * 		(^ Alphabetic ^ Numeric ^ Date ^ !Unsortable ^)
 * 		(| d | 20 | 2008-11-24 | This    |)
 * 		(| b | 8  | 2004-03-01 | column  |)
 * 		(| a | 6  | 1979-07-23 | cannot  |)
 * 		(| c | 4  | 1492-12-08 | be      |)
 * 		(| e | 0  | 1601-08-13 | sorted. |)
 * 
 * inspired by Mediawiki sortable class of wikitable, using jquery.tablesorter.js from http://tablesorter.com/
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Jin Jun (jinjunkr@gmail.com)
 */
 
class NarinPluginInfoSortableTable extends NarinPluginInfo {

	/**
	 * 생성자
	 */		  	
	public function __construct() {
		$this->id = "wiki_sortableTable";
		parent::__construct();
		$this->plugin_path = $this->wiki[path]."/plugins/sortableTable";		// parental class has wrong path name by basename(dirname(__FILE__))
		$this->data_loader_js_file	= $this->data_path."/js/sortableTable_plugin_loader.js";
		$this->data_plugin_js_file	= $this->data_path."/js/jquery.tablesorter.min.js";
		$this->data_css_file		= $this->data_path."/css/sortableTable.css";
		$this->data_css_img_path	= $this->data_path."/css/sortableTable";	
	}	  	

	/**
	 * 플러그인 설명
	 */
	public function description()
	{
		return "Sortable Table 플러그인 (저자 : Jin Jun, jinjunkr@gmail.com)";
	}
	
	
	/**
	 * 플러그인 설정
	 * TODO: 현재는 플러그인 세팅이 필요없지만, uninstall 버튼을 보이게 하기 위해서는 필요
	 */
	public function getSetting() {
		return array(		
			"theme"=>array("type"=>"select", "label"=>"테마", "desc"=>"테스트용", "options"=>array("blue","green"), "value"=>"blue")
		);		
	}
	
	
	/**
	 * 플러그인 인스톨이 필요한가? (DB 추가작업 등)
	 * 필요하다면 return true
	 * 이미 설치되어있거나 설치가 필요없다면 return false
	 */
	public function shouldInstall() {
		// js,css 파일이 없으면 설치 해야 함,  image file 까지 체크해야 할까? -_-
		return !(  file_exists($this->data_loader_js_file)  
				&& file_exists($this->data_plugin_js_file)
				&& file_exists($this->data_css_file)
		);
	}
	
	/**
	 * 플러그인 언인스톨해야 하나?
	 * 이미 설치되어있다면 return true
	 * 그렇지 않다면 return false
	 */
	public function shouldUnInstall() {
		return (   file_exists($this->data_loader_js_file)
				|| file_exists($this->data_plugin_js_file)
				|| file_exists($this->data_css_file)
		);
	}
	
	/**
	 * 플러그인 설치
	 * 이 매소드는 위키 관리의 플러그인 설정에서 설치 버튼 클릭시 수행됨
	 */
	public function install() {
		if(!file_exists($this->data_css_file)) {
			$fp = fopen($this->data_css_file, "w");
			fwrite($fp, $this->css());
			fclose($fp);
		}
		if(!is_dir($this->data_css_img_path)) {
			if(is_file($this->data_css_img_path)) @unlink($this->data_css_img_path);

			mkdir($this->data_css_img_path, 0777);
		}
		copy($this->plugin_path."/inc/blue/asc.gif", $this->data_css_img_path."/asc.gif");
		copy($this->plugin_path."/inc/blue/desc.gif", $this->data_css_img_path."/desc.gif");
		copy($this->plugin_path."/inc/blue/bg.gif", $this->data_css_img_path."/bg.gif");

		if(!file_exists($this->data_plugin_js_file)) {
			copy($this->plugin_path."/inc/jquery.tablesorter.min.js", $this->data_plugin_js_file);
		}		
		if(!file_exists($this->data_loader_js_file)) {
			$fp = fopen($this->data_loader_js_file, "w");
			fwrite($fp, $this->js());
			fclose($fp);
		}
	}
	
	/**
	 * 플러그인 삭제
	 * 이 매소드는 위키 관리의 플러그인 설정에서 제거 버튼 클릭시 수행됨
	 */
	public function uninstall() {
		// data 폴더의 js 파일 삭제
		@unlink($this->data_loader_js_file);
		@unlink($this->data_plugin_js_file);
		@unlink($this->data_css_file);
		$this->rrmdir($this->data_css_img_path);
	}
	
	
	/**
	 * recursive unlink
	 */
	protected function rrmdir($str)
	{
		if(is_file($str)){
			return @unlink($str);
		}
		elseif(is_dir($str)){
			$scan = glob(rtrim($str,'/').'/*');
			foreach($scan as $index=>$path){
				$this->rrmdir($path);
			}
			return @rmdir($str);
		}	
	}
	
	
	protected function css() {
	
		return <<<END
	
	
table.tablesorter thead tr th, table.tablesorter tfoot tr th {
	padding: 2px 20px 2px 5px !important;
}
table.tablesorter thead tr .header {
	background-image: url(sortableTable/bg.gif);
	background-repeat: no-repeat;
	background-position: center right;
	cursor: pointer;
}
table.tablesorter thead tr .unsortable {
	background-color: #ddd;
	padding: 2px 5px !important;
}
table.tablesorter thead tr .headerSortUp {
	background-image: url(sortableTable/asc.gif);
}
table.tablesorter thead tr .headerSortDown {
	background-image: url(sortableTable/desc.gif);
}
table.tablesorter thead tr .headerSortDown, table.tablesorter thead tr .headerSortUp {
	background-color: #8dbdd8;
}


END;
	}
		
	protected function js() {
	
		return <<<END
	
	
$(document).ready(function() 
	{
		$(".tablesorter").tablesorter();
		$(".unsortable").removeClass("header").each(function (index) {
			this.sortDisabled = true;
		});
	}
);
	
			
END;
	}
	
}



?>