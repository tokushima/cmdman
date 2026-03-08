<?php
namespace cmdman;

class Std{
	/**
	 * 標準入力からの入力を取得する
	 */
	public static function read(string $msg, ?string $default=null, array $choice=[], bool $multiline=false, bool $silently=false): ?string{
		while(true){
			$result = $b = null;
			print($msg.(empty($choice) ? '' : ' ('.implode(' / ',$choice).')').(empty($default) ? '' : ' ['.$default.']').': ');

			if($silently && !str_starts_with(PHP_OS,'WIN')){
				`tty -s && stty -echo`;
			}
			while(true){
				fscanf(STDIN,'%s',$b);
				if($multiline && $b === '.'){
					break;
				}
				$result .= $b."\n";

				if(!$multiline){
					break;
				}
			}
			if($silently && !str_starts_with(PHP_OS,'WIN')){
				`tty -s && stty echo`;
			}
			$result = substr(str_replace(["\r\n","\r","\n"],"\n",$result),0,-1);
			if(empty($result)){
				$result = $default;
			}
			if(empty($choice) || in_array($result,$choice)){
				return $result;
			}
		}
	}
	/**
	 * readのエイリアス、入力を非表示にする
	 * Windowsでは非表示になりません
	 */
	public static function silently(string $msg, ?string $default=null, array $choice=[], bool $multiline=false): ?string{
		return self::read($msg,$default,$choice,$multiline,true);
	}

	/**
	 * 色装飾
	 */
	public static function color(string $value, string|bool|null $fmt=null): string{
		if(str_starts_with(PHP_OS,'WIN')){
			$value = mb_convert_encoding($value,'UTF-8','SJIS');
		}else if($fmt !== null){
			$fmt = match(true){
				$fmt === true => '1;34',
				$fmt === false => '1;31',
				default => $fmt,
			};
			$value = "\033[".$fmt.'m'.$value."\033[0m";
		}
		return $value;
	}
	/**
	 * バックスペース
	 */
	public static function backspace(int $len): void{
		print("\033[".$len.'D'."\033[0K");
	}
	/**
	 * プリント
	 */
	public static function print_inline(string $msg, string|int $color=0): void{
		print(self::color($msg,(string)$color));
	}

	/**
	 * 色付きでプリント
	 */
	public static function println(string $msg='', string $color='0'): void{
		print(self::color($msg,$color).PHP_EOL);
	}
	/**
	 * Default
	 */
	public static function println_default(string $msg): void{
		self::println($msg);
	}
	/**
	 * White
	 */
	public static function println_white(string $msg): void{
		self::println($msg,'37');
	}
	/**
	 * Blue
	 */
	public static function println_primary(string $msg): void{
		self::println($msg,'34');
	}
	/**
	 * Green
	 */
	public static function println_success(string $msg): void{
		self::println($msg,'32');
	}
	/**
	 * Cyan
	 */
	public static function println_info(string $msg): void{
		self::println($msg,'36');
	}
	/**
	 * Yellow
	 */
	public static function println_warning(string $msg): void{
		self::println($msg,'33');
	}
	/**
	 * Red
	 */
	public static function println_danger(string $msg): void{
		self::println($msg,'31');
	}
}
