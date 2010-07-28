<?php
if (!defined('APP_ROOT')){
	define('APP_ROOT', dirname(__FILE__));
}
require APP_ROOT . '/core/lib.php';
spl_autoload_register('cron_autoload');

if (defined('IS_SERVER')){
	//server 端
}else{
	script_up();//注册开始
	register_shutdown_function('script_down'); //注册结束
}

?>