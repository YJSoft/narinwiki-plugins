<?
/**
 * 
 * 나린위키 (structured) data 플러그인 : 액션 클래스
 *
 * @package	   narinwiki
 * @subpackage plugin
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Jin Jun (jinjunkr@gmail.com)
 */
class NarinActionData extends NarinActionPlugin {

	/**
	 *
	 * @var string 사용되는 디비 테이블 이름
	 */
	var $db_table;
	
	
	/**
	 * 
	 * 생성자
	 */
	public function __construct() {
		$this->id = "wiki_action_data";		
		parent::__construct();
		$this->db_table = 'byfun_narin_dataplugin';
	}	  	

	/**
	 * 
	 * @see lib/NarinActionPlugin::register()
	 */
	public function register($ctrl)
	{
		$ctrl->addHandler("WRITE_UPDATE_TAIL", $this, "on_write_update_tail");
		$ctrl->addHandler("DELETE_TAIL", $this, "on_delete_tail");
		$ctrl->addHandler("DELETE_ALL", $this, "on_delete_all");	// some reason can't get the proper wr_id_array on DELETE_ALL_TAIL.. so 
		$ctrl->addHandler("PX_DATA_LIST", $this, "on_ajax_call");
	}

	/**
	 *
	 * 문서 등록/수정 시 처리
	 *
	 * @param array $params {@link NarinEvent} 에서 전달하는 파라미터
	 */
	public function on_write_update_tail($params) {
		$wr_id = $params['wr_id'];
		$wr_content = $params['write']['wr_content'];
		$member = $this->member;
		$setting = $this->plugin_info->getPluginSetting();
		$allow_level = $setting['dataentry_allow_level']['value'];
	
		if($allow_level > $member['mb_level'])
		{
			$patternB = '/---- dataentry (.*?)----/s';
			$patternI = '/{{dataentry=(.*?)\?(.*?)}}/s';
				
			if(preg_match($patternB, $wr_content) || preg_match($patternI, $wr_content)) {
				$wikiControl =& wiki_class_load("Control");
				$wikiControl->error("권한 없음", "사용할 수 없는 내용이 있습니다. (dataentry)");
			}
		}
		//TODO: wiki parsing
		// ignore pre, nowiki, code, html
		$wr_content = preg_replace('/&lt;pre&gt;(.*?)&lt;\/pre&gt;/si', '', $wr_content);
		$wr_content = preg_replace('/<pre>(.*?)<\/pre>/si', '', $wr_content);
		$wr_content = preg_replace('/&lt;nowiki&gt;(.*?)&lt;\/nowiki&gt;/si', '', $wr_content);
		$wr_content = preg_replace('/<nowiki>(.*?)<\/nowiki>/si', '', $wr_content);
		$wr_content = preg_replace('/&lt;code&gt;(.*?)&lt;\/code&gt;/si', '', $wr_content);
		$wr_content = preg_replace('/<code>(.*?)<\/code>/si', '', $wr_content);
		$wr_content = preg_replace('/&lt;html&gt;(.*?)&lt;\/html&gt;/si', '', $wr_content);
		$wr_content = preg_replace('/<html>(.*?)<\/html>/si', '', $wr_content);
		
		// clear the previous one: for now no easy way to find updated dataentry.. so delete every dataentry on this page
		$sql_clear = "DELETE FROM ".$this->db_table." WHERE bo_table = '".$this->wiki['bo_table']."' AND wr_id=".$wr_id;
		sql_query($sql_clear);
		
		// find block dataentry
		$pattern = '/---- dataentry (.*?)----(.*?)----/s';
		preg_match_all($pattern, $wr_content, $matches_all, PREG_SET_ORDER);
		foreach ($matches_all as $matches) {
			$keyword = trim($matches[1]);
			$options = preg_split( '/\r\n|\r|\n/', $matches[2]);
			
			$this->insert_dataentry($wr_id, $keyword, $options);
		}
		
		// find inline dataentry
		$pattern = '/{{dataentry=(.*?)\?(.*?)}}/s';
		preg_match_all($pattern, $wr_content, $matches_all, PREG_SET_ORDER);
		foreach ($matches_all as $matches) {
			$keyword = trim($matches[1]);
			$matches[2] = str_replace('=', ":", $matches[2]);
			$options = explode("&", str_replace("&amp;", "&", $matches[2]));
				
			$this->insert_dataentry($wr_id, $keyword, $options);
		}
	}
	
	
	protected function insert_dataentry($wr_id, $keyword, $options) {
		
		// need to parse the content based on "col : val" duples
		// col can have _postfix e.g. _dt (datetime), _page (wikipage), _tag (for datatag output) so on.. see dokuwiki plugin page
		//		col with 's' ending, can have multiple vals delimited with ','
		// content can have # comment
				
		
		foreach($options as $option) {
			if(!$option) continue;
			
			// key(or col) and value..  max 2 parts, so value can have ':' in it.
			$kv = array_map('trim', explode(':', $option, 2));
		
			// col: keep postfix but remove 's', prohibited: 'class''fullpath''docname', any cols starting with '_'
			if(preg_match('/^_/', $kv[0])) continue;
			if($kv[0] == "class" || $kv[0] == "fullpath" || $kv[0] == "docname" ) continue;
				
			// check if col has 's' postfix
			preg_match('/^(.*?)(s?)$/', $kv[0], $kmatch);		// so if want to use col ending 's', append '_' to avoid truncate the last 's'
			$col = $kmatch[1];
			$plural = $kmatch[2];
		
			// comment out
			preg_match('/([^#]*)/', $kv[1], $vmatch);
				
			// val: separte multiple vals into individual
			if($plural) {
				// col ending w/ 's'
				$vals = array_map('trim', explode(',', $vmatch[1]));
				foreach($vals as $val) {
					$sql = "INSERT INTO ".$this->db_table." (bo_table, wr_id, keyword, col, val) VALUES ('".
						$this->bo_table."', ".$wr_id.", '".$keyword."', '".$col."', '".$val."')";
					sql_query($sql);
				}
			}else {
				$val = $vmatch[1];
				$sql = "INSERT INTO ".$this->db_table." (bo_table, wr_id, keyword, col, val) VALUES ('".
					$this->bo_table."', ".$wr_id.", '".$keyword."', '".$col."', '".$val."')";
				sql_query($sql);
			}
		}
	}	

	/**
	 *
	 * 문서 삭제 시 처리
	 *
	 * @param array $params {@link NarinEvent} 에서 전달하는 파라미터
	 */
	public function on_delete_tail($params) {
		$wr_id = $params['write']['wr_id'];
		
		// clear the database entries
		$sql_clear = "DELETE FROM ".$this->db_table." WHERE bo_table = '".$this->wiki['bo_table']."' AND wr_id=".$wr_id;
		sql_query($sql_clear);
	}

	/**
	 *
	 * 여러문서 삭제 시 처리
	 *
	 * @param array $params {@link NarinEvent} 에서 전달하는 파라미터
	 */
	public function on_delete_all($params) {
		foreach($params['chk_wr_id'] as $wr_id) {

			// clear the database entries
			$sql_clear = "DELETE FROM ".$this->db_table." WHERE bo_table = '".$this->wiki['bo_table']."' AND wr_id=".$wr_id;
			sql_query($sql_clear);
		}
	}
	
}


?>