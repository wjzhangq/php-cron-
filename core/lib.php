<?php

/**
 * 延迟执行，如果time=0，则立即执行
 * @param string	$path	脚本路径
 * @param int		$time	推迟执行时间
 */
function setTimeout($path, $time=0){

}

function slog($str){
	$str = '['.date("m-d H:i:s").'] '.$str."\n";
}

function run($path){
	if (defined('PHP_PATH')){
		$cmd = PHP_PATH;
	}else{
		$cmd = 'php';
	}
	
	if ($path{0} != '/'){
		$path = realpath(APP_ROOT . '/' . $path);
	}
	
	$fp = popen($cmd . ' -q ' + $path, 'r');
}

function cron_autoload($className){
	$is_load = false;
	if (is_file(APP_ROOT . '/core/' . $className . '.php')){
		require APP_ROOT . '/core/' . $className . '.php';
		$is_load = true;
	}
	
	if ($is_load){
		if (PHP_VERSION < '5.3' && class_exists($className, false) && method_exists($className, '__static') ) {
			        call_user_func(array($className, '__static'));
			}
	}
}

function script_up(){
	$GLOBALS['cron'] = array('start'=>microtime(true), 'end'=>0, 'run'=>0);
}

function script_down(){
	$GLOBALS['cron']['end'] = microtime(true);
	$GLOBALS['cron']['run'] = $GLOBALS['cron']['end'] - $GLOBALS['cron']['start'];
	
	var_dump($GLOBALS['cron']);
}