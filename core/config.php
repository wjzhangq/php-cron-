<?php
/**
 * 获取配置文件信息
 */

require_once '../init.php';
class config{
	static $data = null;
	
	function __static(){
		self::_config();
	}
	
	/**
	 * 解析配置文件
	 */
	function _config(){
		if (self::$data === null){
			$xml = simplexml_load_file(APP_ROOT . '/data/config.xml');
			self::$data = __toArray($xml);
		}
		
		return self::$data;
	}
	
	function get(){
		$args = func_get_args();
		$ret = self::_config();
		foreach($args as $v){
			if (isset($ret[$v])){
				$ret = $ret[$v];
			}else{
				return null;
			}
		}
		
		return $ret;
	}
}

function __toArray($xml){
	if ($xml instanceof SimpleXMLElement){
		$ret = array();
		$tmp = $xml->children();
		if ($tmp){
			foreach ($tmp as $k=>$v){
				$ret[$k] = __toArray($v);
			}		
		}else{
			$ret =  strip_tags($xml->asXML());
		}
	}else{
		$ret = $xml;
	}
	
	return $ret;
}

if (false){
	var_dump(config::get('Test', 'name'));
}
?>