<?
/**
 * 
 * 나린위키 semantic wiki 플러그인 : 액션 클래스
 *
 * @package	   narinwiki
 * @subpackage plugin
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Jin Jun (jinjunkr@gmail.com)
 */
class NarinActionSemantic extends NarinActionPlugin {
	
	/**
	 * 
	 * 생성자
	 */
	public function __construct() {
		$this->id = "wiki_action_semantic";		
		parent::__construct();
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
		$allow_level = $setting['entry_allow_level']['value'];
	
		if($allow_level > $member['mb_level'])
		{
			$pattern = '/~~SEMANTIC:(.*?)~~/s';
			if(preg_match($pattern, $wr_content)) {
				$wikiControl =& wiki_class_load("Control");
				$wikiControl->error("권한 없음", "사용할 수 없는 내용이 있습니다.");
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
		$sql_clear = "DELETE FROM byfun_narin_dataplugin WHERE bo_table = '".$this->wiki['bo_table']."' AND wr_id=".$wr_id;
		sql_query($sql_clear);
		
		// find semantic dataentry
		if(preg_match('/~~SEMANTIC:(.*?)~~/s', $wr_content, $keywords)) {
			$class = $keywords[1];
			$props = array ();
			$pattern = '/::(.*?):(.*?)::/s';
			preg_match_all($pattern, $wr_content, $matches_all, PREG_SET_ORDER);
			foreach ($matches_all as $matches) {
				$type = trim($matches[1]);
				$val  = trim($matches[2]);
				array_push($props, array("type"=>$type, "val"=>$val));
			}
			$this->insert_dataentry($wr_id, $class, $props);
		}
	}
	
	
	protected function insert_dataentry($wr_id, $class, $props) {
		foreach($props as $p) {
			$type = $p['type'];
			$val  = $p['val'];
				
			// prohibited: 'class''fullpath''docname', any cols starting with '_'
			if(preg_match('/^_/', $type)) continue;
			if($type == "class" || $type == "fullpath" || $type == "docname" ) continue;
				
			// check if col has 's' postfix
			preg_match('/^(.*?)(s?)$/', $type, $kmatch);
			$type = $kmatch[1];
			$plural = $kmatch[2];
		
			// val: separte multiple vals into individual
			if($plural) {
				// col ending w/ 's'
				$vals = array_map('trim', explode(',', $val));
				foreach($vals as $val) {
					$sql = "INSERT INTO byfun_narin_dataplugin (bo_table, wr_id, keyword, col, val) VALUES ('".
						$this->bo_table."', ".$wr_id.", '".$class."', '".$type."', '".$val."')";
					sql_query($sql);
				}
			}else {
				$sql = "INSERT INTO byfun_narin_dataplugin (bo_table, wr_id, keyword, col, val) VALUES ('".
					$this->bo_table."', ".$wr_id.", '".$class."', '".$type."', '".$val."')";
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
		$sql_clear = "DELETE FROM byfun_narin_dataplugin WHERE bo_table = '".$this->wiki['bo_table']."' AND wr_id=".$wr_id;
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
			$sql_clear = "DELETE FROM byfun_narin_dataplugin WHERE bo_table = '".$this->wiki['bo_table']."' AND wr_id=".$wr_id;
			sql_query($sql_clear);
		}
	}
	
}


?>