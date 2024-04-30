<?php
namespace cmdman;

class Args{
	public static $opt = [];
	private static $value = [];
	private static $cmd = '';
	private static $script = '';

	public static function init(int $offset=1){
		$opt = $value = [];
		self::$script = $_SERVER['argv'][0] ?? null;
		$argv = array_slice($_SERVER['argv'] ?? [], $offset);
		
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
	public static function opt(string $name, $default=false){
		return array_key_exists($name,self::$opt) ? self::$opt[$name][0] : $default;
	}
	public static function value($default=null){
		return isset(self::$value[0]) ? self::$value[0] : $default;
	}
	public static function opts(string $name){
		return array_key_exists($name, self::$opt) ? self::$opt[$name] : [];
	}
	public static function values(): array{
		return self::$value;
	}
	public static function cmd(): string{
		return self::$cmd;
	}
	public static function script(): string{
		return self::$script;
	}
}
