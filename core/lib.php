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