<?php
namespace cmdman;

class Args{
	private static $opt = array();
	private static $value = array();
	private static $cmd;

	public static function init(){
		$opt = $value = array();
		$argv = array_slice((isset($_SERVER['argv']) ? $_SERVER['argv'] : array()),1);
			
		if(!empty($argv) && isset($argv[0]) && substr($argv[0],0,1) != '-'){
			self::$cmd = array_shift($argv);
		}
		for($i=0;$i<sizeof($argv);$i++){
			if(substr($argv[$i],0,2) == '--'){
				$opt[substr($argv[$i],2)][] = ((isset($argv[$i+1]) && $argv[$i+1][0] != '-') ? $argv[++$i] : true);
			}else if(substr($argv[$i],0,1) == '-'){
				$n = substr($argv[$i],1);
					
				if(strlen($n) == 1){
					$opt[$argv[$i][1]][] = ((isset($argv[$i+1]) && $argv[$i+1][0] != '-') ? $argv[++$i] : true);
				}else{
					$opt[$n][] = ((isset($argv[$i+1]) && $argv[$i+1][0] != '-') ? $argv[++$i] : true);

					foreach(str_split($n,1) as $k){
						$opt[$k][] = true;
					}
				}
			}else{
				$value[] = $argv[$i];
			}
		}
		self::$opt = $opt;
		self::$value = $value;
	}
	public static function opt($name,$default=false){
		return array_key_exists($name,self::$opt) ? self::$opt[$name][0] : $default;
	}
	public static function value($default=null){
		return isset(self::$value[0]) ? self::$value[0] : $default;
	}
	public static function opts($name){
		return array_key_exists($name,self::$opt) ? self::$opt[$name] : array();
	}
	public static function values(){
		return self::$value;
	}
	public static function cmd(){
		if(defined('CMDMAN_CMD_REPLACE_JSON')){
			$json = constant('CMDMAN_CMD_REPLACE_JSON');
			foreach(json_decode($json,true) as $alias => $real){
				if(strpos(self::$cmd,$alias) === 0){
					self::$cmd = str_replace($alias,$real,self::$cmd);
				}
			}
		}
		return self::$cmd;
	}
}
