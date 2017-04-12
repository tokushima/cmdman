<?php
namespace cmdman;
/**
 * ユーティリティ群
 */
class Util{
	/**
	 * ファイルから取得する
	 * @param string $filename ファイルパス
	 * @return string
	 */
	public static function file_read($filename){
		if(!is_readable($filename) || !is_file($filename)){
			throw new \InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		}
		return file_get_contents($filename);
	}
	/**
	 * ファイルに書き出す
	 * @param string $filename ファイルパス
	 * @param string $src 内容
	 */
	public static function file_write($filename,$src=null,$lock=true){
		if(empty($filename)) throw new \InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		$b = is_file($filename);
		self::mkdir(dirname($filename));
		
		if(false === file_put_contents($filename,(string)$src,($lock ? LOCK_EX : 0))){
			throw new \InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		}
		if(!$b) chmod($filename,0666);
	}
	/**
	 * ファイルに追記する
	 * @param string $filename ファイルパス
	 * @param string $src 内容
	 */
	public static function file_append($filename,$src=null,$lock=true){
		if(empty($filename)) throw new \InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		$b = is_file($filename);
		self::mkdir(dirname($filename));
	
		if(false === file_put_contents($filename,(string)$src,FILE_APPEND|($lock ? LOCK_EX : 0))){
			throw new \InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		}
		if(!$b) chmod($filename,0666);
	}
	/**
	 * フォルダを作成する
	 * @param string $source 作成するフォルダパス
	 * @param oct $permission
	 */
	public static function mkdir($source,$permission=0755){
		$bool = true;
		if(!is_dir($source)){
			try{
				$list = explode('/',str_replace('\\','/',$source));
				$dir = '';
				foreach($list as $d){
					$dir = $dir.$d.'/';
					if(!is_dir($dir)){
						$bool = mkdir($dir);
						if(!$bool) return $bool;
						chmod($dir,$permission);
					}
				}
			}catch(\ErrorException $e){
				throw new \InvalidArgumentException(sprintf('permission denied `%s`',$source));
			}
		}
		return $bool;
	}
	
	/**
	 * 複数プロセスで処理する
	 * dataの数だけプロセスをフォークします
	 * @param callable $callback 処理する関数
	 * @param array $data 処理対象のデータ
	 */
	public static function pctrl(callable $callback,array $data){
		foreach($data as $key => $param){
			$pid = pcntl_fork();
			
			if($pid === 0){
				// child process
				$rtn = call_user_func_array($callback,[$param,$key]);
				exit;
			}else{
				// parent process
			}
		}
		// 子プロセス終了待ち
		while(pcntl_waitpid(0,$status) !== -1);
	}
	
	/**
	 * エラーとして終了する
	 */
	public static function exit_error(){
		exit(1);
	}
	/**
	 * 一時停止として終了する
	 */
	public static function exit_wait(){
		exit(19);
	}
}
