<?php
/**
 * 
作者：Colt Ma 
网址：http://php.qseek.net 
版权所有：Colt Ma 
加入我们：如果你喜欢PHP，也喜欢开源，那就加入我们吧！ 
联系：qseek#163.com 请把#改为@ 
版本：v1.2 build 2008-02-21  *
 */

class Qtxtdb {
	// public
	var $database = '.'; // 文本数据表所在的目录地址
	var $table; // 当前文本数据表
		// $fields 数组的格式：键名=>值 对应为 字段名称 => 字段长度
		// 比如：$fields = array('字段1' => 5,'字段2' => 15); 
	var $fields = array(); // 数据表的字段
	var $count; // 纪录总数，不含表头。
	var $content; // 文本数据表的全文，用于搜索

	// 格式化输出结果记录。default:默认数字为键名的数组；field:字段名为键名的数组；table:表格；xml:xml格式；等等。可以自行扩展。
	var $resultFormat = 'field'; 
	var $version = 'v1.2 build 2008-02-21';
	
	// private
	var $fp;
	var $recordLength;
	var $padStr = ' ';
	var $delimiter = ',';
	var $enclosure = '"';
	var $_error = array();
	
	// public
	function Qtxtdb($database='') {
		if (!empty($database)) {
			$this->connect($database);
		}
	}

	function connect($database='') {
		if (!empty($database) and is_dir($database)) {
			$this->database = rtrim($database,'/');
			return true;
		}
		else {
			$this->_error('Fail to connect to the database.'." @ \nFILE: ".__FILE__." , \nLINE:".__LINE__." , \nFUNCTION:".__FUNCTION__." .",'db');
			return false;
		}
	}

	function close($table='') {
		if (empty($table) and isset($this->fp[$this->table])) {
			fclose($this->fp[$this->table]);
			unset($this->fp[$this->table]);
			$this->table = '';
		}
		elseif (isset($this->fp[$table])) {
			fclose($this->fp[$table]);
			unset($this->fp[$table]);
		}
	}

	function closeAll() {
		foreach ($this->fp as $k => $v) {
			fclose($v);
			unset($this->fp[$k]);
		}
		$this->table = '';
	}
	
	function selectTable($table='') {
		if (!empty($table)) {
			$this->table = $table;
		}
		if (empty($this->table)) {
			$this->_error('Table filename is empty.'." @ \nFILE: ".__FILE__." , \nLINE:".__LINE__." , \nFUNCTION:".__FUNCTION__." .",'table');
			return false;
		}
		if  (!isset($this->fp[$this->table])) {
			$fn = "{$this->database}/{$this->table}";
			if (!is_writable($fn))
				chmod($fn,0777);
			if ($this->fp[$this->table] = fopen($fn,'r+')) {
				$this->_getHeader();
			}
		}
		return true;
	}

	function switchTable($table='') {
		if (isset($this->fp[$table])) {
			$this->table = $table;
		}
		else {
			$this->selectTable($table);
		}
	}

	function createTable($table='',$fields=array()) {
		// $fields 数组的格式：键名=>值 对应为 字段名称 => 字段长度
		// 比如：$fields = array('字段1' => 5,'字段2' => 15); 
		$tmp = array();
		if (!empty($table) and !empty($fields)) {
			$fn = "{$this->database}/{$table}";
			if (file_exists($fn) and !is_writable($fn))
				chmod($fn,0777);
			if ($this->fp[$table] = fopen($fn,'w')) {
				$this->table = $table;
				foreach ($fields as $k => $v) {
					$tmp[$k] = $v > strlen($k) ? $v : strlen($k);
				}
				$this->fields[$table] = $tmp;
				$this->_write(array_keys($tmp));
				$this->close($table);
				return true;
			}
			else {
				$this->_error('Fail to open the table file.'." @ \nFILE: ".__FILE__." , \nLINE:".__LINE__." , \nFUNCTION:".__FUNCTION__." .",'table');
				return false;
			}
		}
		else {
			$this->_error('Unleagle table option.'." @ \nFILE: ".__FILE__." , \nLINE:".__LINE__." , \nFUNCTION:".__FUNCTION__." .",'table');
			return false;
		}
	}

	// 以下是典型的 CRUD 操作： 新增记录、读取纪录、更新记录、删除记录
	// $record 是记录数组，记录字段数必须与 对应的表的字段 完全一致。
	function add($record=array()) {
		fseek($this->fp[$this->table],0,SEEK_END);
		$this->_write($record);
		$this->count[$this->table]++;
		return $this->count[$this->table];
	}

	// 按照字段的ID查询记录。ID就是记录的顺序号。是自动的。
	// 每次返回1条ID为 $id 的纪录。
	function find($id=0) {
		if (empty($id) or ($id<1) or ($id > $this->count[$this->table]))
			return false;
		rewind($this->fp[$this->table]);
		fseek($this->fp[$this->table],$id*$this->recordLength[$this->table]);
		$res = $this->_read();
		if (isset($res[0]) and !empty($res[0]) and !ereg('^ +$',$res[0])) {
			$res = $this->_unFormatRecord($res);
			$res = $this->formatResult($res);
			return $res;
		}
		else
			return false;
	}

	// 按照字段的ID查询记录。ID就是记录的顺序号。是自动的。
	// 每次返回N条纪录。$ids 为空或者为*则返回所有记录。$ids为'3,16'则返回第3~16条记录。
	function findAll($ids='') {
		$res = array();
		if (!is_array($ids)) {
			if (empty($ids) or $ids== '*') {
				$ids = range(1,$this->count[$this->table]);
			}
			elseif (ereg(',',$ids)) {
				$tmp = explode(',',$ids);
				$ids = range($tmp[0],$tmp[1]);
			}
			else 
				return $res;
		}
		foreach ($ids as $id) {
			if ($id > $this->count[$this->table]) {
				continue;
			}
			$tmp = $this->find($id);
			if ($tmp)
				$res[] = $tmp;
		}
		return $res;
	}

	// 更新第 $id 条记录。
	function update($id='',$record=array()) {
		if (!(empty($id) or ($id<1) or ($id > $this->count[$this->table]))) {
			fseek($this->fp[$this->table],$id*$this->recordLength[$this->table]);
			$this->_write($record);
		}
	}

	// 删除第 $id 条记录。
	function delete($id='') {
		if (!(empty($id) or ($id<1) or ($id > $this->count[$this->table]))) {
			$record = array_pad(array(),count($this->fields[$this->table]),'');
			fseek($this->fp[$this->table],$id*$this->recordLength[$this->table]);
			$this->_write($record);
	// 不减少总记录数，因为被删除的记录所占据的位置还在那里。这样不至于扰乱其他记录的ID号。
	//		$this->count[$this->table]--;
		}
	}

	// 搜索关键字为$str的纪录。
	function search($str='',$regex=false) {
		if (empty($str))
			return false;
		if ($regex) {
			$pattern = $str;
		}
		else {
			$str = preg_quote($str,'/');
			$pattern = "{$this->enclosure}.*{$str}.*{$this->enclosure}\n";
		}
		if (!isset($this->content[$this->table]))
			$this->_readTable();
		$res = '';
		preg_match_all("/$pattern/i",$this->content[$this->table],$res);
		$res = $res[0];
		if (count($res)>0) {
			foreach($res as $k => $v) {
				$v = str_getcsv($v,$this->delimiter,$this->enclosure);
				$res[$k] = $this->_unFormatRecord($v[0]);
				$res[$k] = $this->formatResult($res[$k]);
			}
		}
		return $res;
	}

	// 搜索字段$name中关键字为$str的纪录。
	function searchByField($name='',$str='',$exactly=false) {
		if (empty($name) or empty($str))
			return false;
		$fields = $this->getFields();
		$regex = array();
		foreach($fields as $v) {
			if ($v != $name)
				$regex[] = "{$this->enclosure}[^{$this->enclosure}]+{$this->enclosure}";
			else {
				if ($exactly) 
				$regex[] = "{$this->enclosure}{$str}{$this->padStr}*{$this->enclosure}";
				else
				$regex[] = "{$this->enclosure}[^{$this->enclosure}]*{$str}[^{$this->enclosure}]*{$this->enclosure}";
			}
		}
		$str = implode($this->delimiter,$regex);
		return $this->search($str,true);
	}

	// 搜索关键字为$str的纪录，返回记录的ID。目前还不完善。
	function getID($str='') {
		if (empty($str))
			return false;
		$str = preg_quote($str,'/');
		if (!isset($this->content[$this->table]))
			$this->_readTable();
		$pattern = "{$this->enclosure}([0-9]+).*{$str}.*{$this->enclosure}\n";
		$res = '';
		preg_match("/$pattern/i",$this->content[$this->table],$res);
		if (isset($res[1]) and !empty($res[1])) {
			return $res[1];
		}
		return 0;
	}

	// 返回符合条件的记录总数。默认为所有记录数。
	function getCount($str='',$regex=false) {
		if (empty($str)) 
			return $this->count[$this->table];
		return count($this->search($str,$regex));
	}

	function getCountBlank() {
		$regex = array();
		$regex = array_pad($regex,count($this->getFields()),"{$this->enclosure}{$this->padStr}*{$this->enclosure}");
		$str = implode($this->delimiter,$regex);
		return count($this->search($str,true));
	}

	// 返回当前表的名称。
	function getTable() {
		return $this->table;
	}

	// 返回当前表的字段名称。
	function getFields() {
		return array_keys($this->fields[$this->table]);
	}

	// 格式化结果记录。
	function formatResult($record=array()) {
		if (empty($record) or !is_array($record))
			return false;
		$method = '_resultIn'.ucfirst($this->resultFormat);
		if (empty($this->resultFormat) or ($this->resultFormat == 'default') or !method_exists($this,$method)) {
			return $record;
		}
		else {
			return $this->$method($record);
		}

	}

	function setResultFormat($format='') {
		$this->resultFormat = $format;
	}

	// DEBUG
	function debug($tag='') {
		echo '<div style="width:75%;float:middle"><fieldset style="background-color:#FFC"><legend style="font-weight:bold;color:#00F">Global error info</legend>';
		echo '<pre>';
		if (!empty($tag) and array_key_exists($tag,$this->_error))
			print_r($this->_error[$tag]);
		else
			print_r($this->_error);
		echo '</pre>';
		echo "</fieldset></div>";
	}


	// 格式化普通的CSV文件为QTxtDB格式
	function csvToQtxtdb($file='',$fieldsLength=array()) {
		if (empty($file) or !file_exists($file))
			return false;
		if (empty($fieldsLength) or count($fieldsLength)<1)
			$fieldsLength = $this->_genFieldsLength($file);
		if (count($fieldsLength)<1)
			return false;
		copy($file,$file.'.bak');
		$db = new Qtxtdb('.');
		$created = false;
		$handle = fopen($file.'.bak','r');
		while ($data = fgetcsv($handle, 4096)) {
			if (!$created) {
				$data = (count($data) < count($fieldsLength))? array_pad($data,count($fieldsLength),'') : $data;
				$fields = array_combine($data,$fieldsLength);
				$db->createTable(basename($file),$fields);
				$db->selectTable();
				$created = true;
			}
			else {
				$db->add($data);
			}
		}
		fclose($handle);
		$db->close();
		return true;
	}

	// 格式化QTxtDB格式文件为普通的CSV
	function QtxtdbToCsv($file='') {
		if (empty($file) or !file_exists($file))
			return false;
		$txt = implode('',file($file));
		$txt = preg_replace('/'.$this->padStr.'*'.$this->enclosure.'/',$this->enclosure,$txt);
		copy($file,$file.'.bak');
		$fp = fopen($file,'w');
		fputs($fp,$txt);
		fclose($fp);
		return true;
	}

	function version() {
		return $this->version;
	}

	// private
	function _getHeader() {
		$tmp = fgetcsv($this->fp[$this->table],4000,$this->delimiter,$this->enclosure);
		$this->fields[$this->table] = array();
		foreach ($tmp as $v) {
			$k = rtrim($v,$this->padStr);
			$this->fields[$this->table][$k] = strlen($v);
		}
		$this->recordLength[$this->table] = array_sum($this->fields[$this->table]) + count($this->fields[$this->table])*3;
		$this->count[$this->table] = floor(filesize("{$this->database}/{$this->table}")/$this->recordLength[$this->table]) - 1;
	}

	function _formatRecord($record=array()) {
		$record = str_replace($this->enclosure, $this->enclosure.$this->enclosure, $record);
		$record = str_replace("\n", chr(3), $record);
//		print_r($record );
		foreach ($record as $k => $v) {
			$len = current($this->fields[$this->table]);
	//		echo $len.'<BR>'.$v;
			if (strlen($v) > $len) {
				$record[$k] = $this->_fixLen($v,$len);
			}
			if (strlen($v) < $len)
				$record[$k] = str_pad($record[$k],$len,$this->padStr);
			next($this->fields[$this->table]);
		}
		reset($this->fields[$this->table]);
		return $record;
	}

	function _unFormatRecord($record=array()) {
		foreach ($record as $k => $v) {
			$record[$k] = rtrim($v,$this->padStr);
			$record[$k] = str_replace(chr(3), "\n", $record[$k]);
		}
		return $record;
	}

	function _fixLen($str='',$len=0) { 
		if($len>=strlen($str) or $len <= 0)
			return $str;
		$str = substr($str,0,$len);
		$p = "[".chr(0xa1)."-".chr(0xff)."]+$";
		$res = array();
		preg_match("/$p/",$str,$res);
		if (isset($res[0]) and fmod(strlen($res[0]),2) == 1)
			$str = substr($str,0,$len-1);
		return $str;
	} 

	function _fixRecord($record=array()) {
		$c = count($this->fields[$this->table]);
		$r = count($record);
		if ($r == $c)
			return $record;
		if ($r<$c)
			$res = array_pad($record,$c,'');
		else {
			$res = array_slice($record,0,$c);
		}
		return $res;
	}

	function _write($record=array()) {
		$record = $this->_fixRecord($record);
		$record = $this->_formatRecord($record);
	//	fputcsv($this->fp[$this->table],$record,$this->delimiter,$this->enclosure);
		$str = implode("{$this->enclosure}{$this->delimiter}{$this->enclosure}", $record);
		$str = $this->enclosure.$str.$this->enclosure."\n";
		flock($this->fp[$this->table], LOCK_EX);
		if (!fwrite($this->fp[$this->table],$str)) {
			$this->_error('fail to write the record'." @ \nFILE: ".__FILE__." , \nLINE:".__LINE__." , \nFUNCTION:".__FUNCTION__." .",'write');
		}
		flock($this->fp[$this->table], LOCK_UN);
	}

	function _read() {
		return fgetcsv($this->fp[$this->table],$this->recordLength[$this->table],$this->delimiter,$this->enclosure);
	}

	function _readTable() {
		$tmp = file("{$this->database}/{$this->table}");
		unset($tmp[0]);
		$this->content[$this->table] = implode('',$tmp);
		return $this->content[$this->table];
	}

	function _countArrayLength($array = array()) {
		$res = array();
		foreach($array as $v) {
			$res[] = strlen($v);
		}
		return $res;
	}

	function _genFieldsLength($file='') {
		if (empty($file) or !file_exists($file))
			return false;
		$handle = fopen($file,'r');
		while ($data = fgetcsv($handle, 4096)) {
			if (!isset($res))
				$res = $this->_countArrayLength($data);
			else {
				$res2 = $this->_countArrayLength($data);
				$res = $this->_maxArrayLength($res,$res2);
			}
		}
		fclose($handle);
		return (isset($res))? $res : false;
	}

	function _maxArrayLength($a1=array(),$a2=array()) {
		$n = count($a1);
		$n2 = count($a2);
		if ($n>$n2) {
			$a2 = array_pad($a2,$n,0);
		}
		if ($n<$n2) {
			$n = $n2;
			$a1 = array_pad($a1,$n,0);
		}
		for ($i=0;$i<$n;$i++) {
			$a1[$i] = ($a1[$i] > $a2[$i])? $a1[$i] : $a2[$i];
		}
		return $a1;
	}

	function _resultInField($record=array()) {
		return array_combine($this->getFields(),$record);
	}

	function _error($msg='',$tag='misc') {
		if (!empty($msg)) {
			if (empty($tag)) 
				$tag = 'misc';
			$this->_error[$tag][] = $msg;
		}
	}


}

if (!function_exists('str_getcsv')):

function str_getcsv($str, $separador = ',', $delimitador = '"') {

    $md5_separador       = md5($separador);
    $md5_separador_linha = md5(time());

    $buf = '';
    $len = strlen($str);
    $aberto = false;

    for ($i = 0; $i < $len; $i++) {
        $c = $str[$i];
        switch ($c) {
        case $separador:
            if ($aberto) {
                $buf .= $c;
            } else {
                $buf .= $md5_separador;
            }
            break;
        case $delimitador:
            if ((($i+1)<$len) and ($str[$i + 1] == $delimitador)) {
                $buf .= $delimitador;
                $i++;
            } else {
                $aberto = !$aberto;
            }
            break;
        case "\n":
            if ($aberto) {
                $buf .= $c;
            } else {
                $buf .= $md5_separador_linha;
            }
            break;
        default:
            $buf .= $c;
            break;
        }
    }

    // Quebrando em linhas
    $linhas = explode($md5_separador_linha, $buf);

    // Para cada linha, quebrar em dados
    $retorno = array();
    foreach ($linhas as $linha) {
        $retorno[] = explode($md5_separador, $linha);
    }
    return $retorno;
}

endif;

if (!function_exists('array_combine')):

function array_combine($key=array(),$val=array()) {
	if (empty($key) or empty($val)) {
		return array();
	}
	$res = array();
	foreach($key as $k) {
		$res[$k] = array_shift($val);
	}
	return $res;
}

endif;

?>