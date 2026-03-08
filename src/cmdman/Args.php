<?php
namespace cmdman;

class Args{
	public static array $opt = [];
	private static array $value = [];
	private static string $cmd = '';
	private static string $script = '';

	public static function init(int $offset=1): void{
		$opt = $value = [];
		self::$script = $_SERVER['argv'][0] ?? '';
		$argv = array_slice($_SERVER['argv'] ?? [], $offset);

		if(!empty($argv) && isset($argv[0]) && !str_starts_with($argv[0],'-')){
			self::$cmd = array_shift($argv);
		}
		for($i=0;$i<count($argv);$i++){
			if(str_starts_with($argv[$i],'--')){
				$opt[substr($argv[$i],2)][] = ((isset($argv[$i+1]) && $argv[$i+1][0] !== '-') ? $argv[++$i] : true);
			}else if(str_starts_with($argv[$i],'-')){
				$n = substr($argv[$i],1);

				if(strlen($n) === 1){
					$opt[$argv[$i][1]][] = ((isset($argv[$i+1]) && $argv[$i+1][0] !== '-') ? $argv[++$i] : true);
				}else{
					$opt[$n][] = ((isset($argv[$i+1]) && $argv[$i+1][0] !== '-') ? $argv[++$i] : true);

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
	public static function opt(string $name, mixed $default=false): mixed{
		return array_key_exists($name,self::$opt) ? self::$opt[$name][0] : $default;
	}
	public static function value(mixed $default=null): mixed{
		return self::$value[0] ?? $default;
	}
	public static function opts(string $name): array{
		return self::$opt[$name] ?? [];
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
