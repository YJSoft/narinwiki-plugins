<?
/**
 * 
 * 나린위키 (structured) data 플러그인 : 플러그인 정보 클래스
 *
 * Narinwiki porting of Dokuwiki include plugin (http://www.dokuwiki.org/plugin:data)
 *
 * @package	   narinwiki
 * @subpackage plugin
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Jin Jun (jinjunkr@gmail.com)
 */
 
class NarinPluginInfoData extends NarinPluginInfo {

	/**
	 *
	 * @var string 사용되는 디비 테이블 이름
	 */
	var $db_table;
	
	/**
	 * 생성자
	 */
	public function __construct() {
		
		parent::__construct();			
		
		$this->id = "wiki_data";				
		
		// {@link NarinPluginInfo} 클래스의 생성자에서 getSetting() 을 호출함		
		$this->init();
		$this->db_table = "byfun_narin_dataplugin";
	}

	/**
	 * 
	 * 플러그인 설명
	 * 
	 * @return string 플러그인 설명
	 */
	public function description()
	{
		return "Data 플러그인 (저자 : Jin Jun, jinjunkr@gmail.com)";
	}


	/**
	 *
	 * @see lib/NarinPluginInfo::getSetting()
	 */
	public function getSetting() {
		$css = file_exists($this->data_css_file) ? file_get_contents($this->data_css_file) : file_get_contents($this->plugin_path."/data.css");
		return array(
			"dataentry_allow_level"=>array("type"=>"select", 
					"label"=>"dataentry 사용 권한", 
					"desc"=>"설정된 권한보다 낮은 레벨의 사용자가 작성한 dataentry는 무시됩니다.", 
					"options"=>array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10), 
					"value"=>2),
			"template_ns"=>array("type"=>"text", 
					"label"=>"문서틀 폴더 ", 
					"desc"=>"예를 들어, '/틀'로 지정하면, ---- dataentry project ---- 로 입력된 내용이 '/틀/project' 문서틀을 사용해서 보입니다. 
					이 기능을 사용하려면 문서틀(template) 플러그인이 설치되어 있어야 합니다.", 
					"value"=>"/틀"),
		);
	}

	
	
	/**
	 * 
	 * 플러그인 인스톨이 필요한가? (DB 추가작업)
	 * 
	 * @see lib/NarinPluginInfo::shouldInstall()
	 */
	public function shouldInstall() {
		$sql = "SHOW TABLES LIKE '".$this->db_table."'";
		$res = sql_query($sql);
		if(mysql_num_rows($res)>0) return false;
		else return true;
	}
	
	/**
	 * 
	 * 플러그인 언인스톨해야 하나?
	 * 
	 * @see lib/NarinPluginInfo::shouldUnInstall()
	 */
	public function shouldUnInstall() {
		$sql = "SHOW TABLES LIKE '".$this->db_table."'";
		$res = sql_query($sql);
		if(mysql_num_rows($res)>0) return true;
		else return false;
	}
	
	/**
	 * 
	 * 플러그인 설치
	 * 
	 * @see lib/NarinPluginInfo::install()
	 */
	public function install() {
		$sql = "CREATE TABLE IF NOT EXISTS `".$this->db_table."` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `bo_table` varchar(20) NOT NULL,
				  `wr_id` int(11) NOT NULL,
				  `keyword` varchar(50) NOT NULL,
				  `col` varchar(50) NOT NULL,
				  `val` varchar(255) NOT NULL,
				  PRIMARY KEY (`id`),
				  KEY `idx_board_wr_id` (`bo_table`,`wr_id`,`keyword`,`col`)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;";

		sql_query($sql);
	}
	
	/**
	 * 
	 * 플러그인 삭제
	 * 
	 * @see lib/NarinPluginInfo::uninstall()
	 */
	public function uninstall() {
		sql_query("DROP TABLE `".$this->db_table."`");
	}
}

?>