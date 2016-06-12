<?php
namespace cmdman;
/**
 * ユーティリティ群
 */
class Util{
	/**
	 * ファイルに書き出す
	 * @param string $filename ファイルパス
	 * @param string $src 内容
	 */
	public static function file_write($filename,$src=null,$lock=true){
		if(empty($filename)) throw new \InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		$b = is_file($filename);
		self::mkdir(dirname($filename));
		if(false === file_put_contents($filename,(string)$src,($lock ? LOCK_EX : 0))) throw new \InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		if(!$b) chmod($filename,0777);
	}
	/**
	 * フォルダを作成する
	 * @param string $source 作成するフォルダパス
	 * @param oct $permission
	 */
	public static function mkdir($source,$permission=0775){
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
		foreach($data as $param){
			$pid = pcntl_fork();
			
			if($pid === 0){
				// child process
				$rtn = call_user_func_array($callback,[$param]);
				exit;
			}else{
				// parent process
			}
		}
		// 子プロセス終了待ち
		while(pcntl_waitpid(0,$status) !== -1);
	}
}
