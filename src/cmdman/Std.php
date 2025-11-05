<?php
namespace cmdman;

class Std{
	/**
	 * 標準入力からの入力を取得する
	 * @param string $msg 入力待ちのメッセージ
	 * @param string $default 入力が空だった場合のデフォルト値
	 * @param string[] $choice 入力を選択式で求める
	 * @param boolean $multiline 複数行の入力をまつ、終了は行頭.(ドット)
	 * @param boolean $silently 入力を非表示にする(Windowsでは非表示になりません)
	 */
	public static function read(string $msg, $default=null, array $choice=[], bool $multiline=false, bool $silently=false): ?string{
		while(true){
			$result = $b = null;
			print($msg.(empty($choice) ? '' : ' ('.implode(' / ',$choice).')').(empty($default) ? '' : ' ['.$default.']').': ');

			if($silently && substr(PHP_OS,0,3) != 'WIN'){
				`tty -s && stty -echo`;
			}
			while(true){
				fscanf(STDIN,'%s',$b);
				if($multiline && $b == '.'){
					break;
				}
				$result .= $b."\n";

				if(!$multiline){
					break;
				}
			}
			if($silently && substr(PHP_OS,0,3) != 'WIN'){
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
	 * @param string $msg 入力待ちのメッセージ
	 * @param string $default 入力が空だった場合のデフォルト値
	 * @param string[] $choice 入力を選択式で求める
	 * @param boolean $multiline 複数行の入力をまつ、終了は行頭.(ドット)
	 */
	public static function silently(string $msg, $default=null, array $choice=[], bool $multiline=false): ?string{
		return self::read($msg,$default,$choice,$multiline,true);
	}

	/**
	 * 色装飾
	 * @param string $value
	 * @param mixed $fmt
	 */
	public static function color(string $value, $fmt=null): string{
		if(substr(PHP_OS,0,3) == 'WIN'){
			$value = mb_convert_encoding($value,'UTF-8','SJIS');
		}else if($fmt !== null){
			$fmt = ($fmt === true) ? '1;34' : (($fmt === false) ? '1;31' : $fmt);
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
	 * @param string $msg
	 * @param string $color ANSI Colors
	 */
	public static function p(string $msg, $color=0): void{
		print(self::color($msg,$color));
	}
	
	/**
	 * 色付きでプリント
	 * @param string $msg
	 * @param string $color ANSI Colors
	 */
	public static function println($msg='',$color='0'): void{
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
	 * @param string $msg
	 */
	public static function println_white(string $msg): void{
		self::println($msg,'37');
	}
	/**
	 * Blue
	 * @param string $msg
	 */
	public static function println_primary(string $msg): void{
		self::println($msg,'34');
	}
	/**
	 * Green
	 * @param string $msg
	 */
	public static function println_success(string $msg): void{
		self::println($msg,'32');
	}
	/**
	 * Cyan
	 * @param string $msg
	 */
	public static function println_info(string $msg): void{
		self::println($msg,'36');
	}
	/**
	 * Yellow
	 * @param string $msg
	 */
	public static function println_warning(string $msg): void{
		self::println($msg,'33');
	}
	/**
	 * Red
	 * @param string $msg
	 */
	public static function println_danger(string $msg): void{
		self::println($msg,'31');
	}
}