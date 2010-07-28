<?php
require_once '../init.php';
require APP_ROOT . '/core/3rd/qtxtdb.class.php';
class cronData{
	var $db = null;
	
	/**
	 * 构造函数
	 */
	function __construct(){
		$this->db = new Qtxtdb(APP_ROOT . '/data/core');
		if (!file_exists(APP_ROOT . '/data/core/cron')){
			$this->db->createTable('cron', array('code'=>20, 'plan_time'=>11, 'start_time'=>11, 'run_time'=>11, 'add_time'=>11, 'update_time'));
		}
		if (!file_exists(APP_ROOT . '/data/core/once')){
			$this->db->createTable('once', array('code'=>20, 'plan_time'=>11, 'add_time'=>11));
		}
	}
	
	
	/**
	 * 获取所有任务
	 */
	function listAll(){
		$this->db->switchTable('cron');
		$list = $this->db->findAll();
		return $list;
	}
	
	/**
	 * 获取当前能执行的任务
	 * @param int $now		当前时间戳
	 * @param int $timeout	超时时间
	 */
	function listCurr($now){
		$all = $this->listAll();
		$curr = array();
		foreach($all as $v){
			if ($v['plan_time'] <= $now){
				$curr[] = $v;
			}
		}
		
		return $curr;
	}
	
	function update($item){
		
	}
}

if (true){
	$a = new cronData();
}
?>