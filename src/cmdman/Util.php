<?php
namespace cmdman;
/**
 * ユーティリティ群
 */
class Util{
	/**
	 * ファイルから取得する
	 */
	public static function file_read(string $filename): string{
		if(!is_readable($filename) || !is_file($filename)){
			throw new \cmdman\AccessDeniedException(sprintf('permission denied `%s`',$filename));
		}
		return file_get_contents($filename);
	}

	/**
	 * ファイルに書き出す
	 */
	public static function file_write(string $filename, ?string $src=null, bool $lock=true): void{
		if(empty($filename)){
			throw new \cmdman\AccessDeniedException(sprintf('permission denied `%s`',$filename));
		}
		$b = is_file($filename);
		self::mkdir(dirname($filename));
		
		if(false === file_put_contents($filename,(string)$src,($lock ? LOCK_EX : 0))){
			throw new \cmdman\AccessDeniedException(sprintf('permission denied `%s`',$filename));
		}
		if(!$b){
			chmod($filename,0666);
		}
	}
	/**
	 * ファイルに追記する
	 */
	public static function file_append(string $filename, ?string $src=null, bool $lock=true): void{
		if(empty($filename)){
			throw new \cmdman\AccessDeniedException(sprintf('permission denied `%s`',$filename));
		}
		$b = is_file($filename);
		self::mkdir(dirname($filename));
	
		if(false === file_put_contents($filename,(string)$src,FILE_APPEND|($lock ? LOCK_EX : 0))){
			throw new \cmdman\AccessDeniedException(sprintf('permission denied `%s`',$filename));
		}
		if(!$b){
			chmod($filename,0666);
		}
	}
	/**
	 * フォルダを作成する
	 */
	public static function mkdir(string $source, $permission=0755): bool{
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
				throw new \cmdman\AccessDeniedException(sprintf('permission denied `%s`',$source));
			}
		}
		return $bool;
	}
	/**
	 * 移動
	 */
	public static function mv(string $source, string $dest): bool{
		if(is_file($source) || is_dir($source)){
			self::mkdir(dirname($dest));
			return rename($source,$dest);
		}
		throw new \cmdman\AccessDeniedException(sprintf('permission denied `%s`',$source));
	}
	/**
	 * 削除
	 * $sourceがフォルダで$inc_selfがfalseの場合は$sourceフォルダ以下のみ削除
	 */
	public static function rm(string $source, bool $inc_self=true): void{
		if(is_dir($source)){
			$source = realpath($source);
			$dir = [];
			
			$it = new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator($source,\FilesystemIterator::CURRENT_AS_FILEINFO|\FilesystemIterator::UNIX_PATHS)
					);
			foreach($it as $f){
				if($f->getFilename() == '.'){
					if($inc_self || $source != $f->getPath()){
						$dir[$f->getPath()] = 1;
					}
				}else if($f->getFilename() != '..'){
					unlink($f->getPathname());
				}
			}
			krsort($dir);
			
			foreach(array_keys($dir) as $d){
				rmdir($d);
			}
		}else if(is_file($source)){
			unlink($source);
		}
	}
	/**
	 * コピー
	 * $sourceがフォルダの場合はそれ以下もコピーする
	 */
	public static function copy(string $source, string $dest): void{
		if(is_dir($source)){
			$source = realpath($source);
			$len = strlen($source);
			
			self::mkdir($dest);
			$dest = realpath($dest);
			
			foreach(self::ls($source,true) as $f){
				$destp = $dest.'/'.substr($f->getPathname(),$len);
				self::mkdir(dirname($destp));
				copy($f->getPathname(),$destp);
			}
		}else if(is_file($source)){
			self::mkdir(dirname($dest));
			copy($source,$dest);
		}else{
			throw new \cmdman\AccessDeniedException(sprintf('permission denied `%s`',$source));
		}
	}
	/**
	 * ディレクトリ内のファイル・ディレクトリ
	 */
	public static function ls(string $directory, bool $recursive=false, ?string$pattern=null): \RecursiveDirectoryIterator{
		$directory = self::parse_filename($directory);
		
		if(is_file($directory)){
			$directory = dirname($directory);
		}
		if(is_dir($directory)){
			$it = new \RecursiveDirectoryIterator($directory,\FilesystemIterator::CURRENT_AS_FILEINFO|\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::UNIX_PATHS);
			if($recursive){
				$it = new \RecursiveIteratorIterator($it);
			}
			if(!empty($pattern)){
				$it = new \RegexIterator($it,$pattern);
			}
			return $it;
		}
		throw new \cmdman\AccessDeniedException(printf('permission denied `%s`',$directory));
	}
	private static function parse_filename(string $filename): string{
		$filename = preg_replace("/[\/]+/",'/',str_replace("\\",'/',trim($filename)));
		return (substr($filename,-1) == '/') ? substr($filename,0,-1) : $filename;
	}
	/**
	 * 絶対パスを返す
	 */
	public static function path_absolute(?string $a, ?string $b): string{
		if($b === '' || $b === null) return $a;
		if($a === '' || $a === null || preg_match("/^[a-zA-Z]+:/",$b)) return $b;
		if(preg_match("/^[\w]+\:\/\/[^\/]+/",$a,$h)){
			$a = preg_replace("/^(.+?)[".(($b[0] === '#') ? '#' : "#\?")."].*$/","\\1",$a);
			if($b[0] == '#' || $b[0] == '?') return $a.$b;
			if(substr($a,-1) != '/') $b = (substr($b,0,2) == './') ? '.'.$b : (($b[0] != '.' && $b[0] != '/') ? '../'.$b : $b);
			if($b[0] == '/' && isset($h[0])) return $h[0].$b;
		}else if($b[0] == '/'){
			return $b;
		}
		$p = [
				['://','/./','//'],
				['#R#','/','/'],
				["/^\/(.+)$/","/^(\w):\/(.+)$/"],
				["#T#\\1","\\1#W#\\2",''],
				['#R#','#W#','#T#'],
				['://',':/','/']
		];
		$a = preg_replace($p[2],$p[3],str_replace($p[0],$p[1],$a));
		$b = preg_replace($p[2],$p[3],str_replace($p[0],$p[1],$b));
		$d = $t = $r = '';
		if(strpos($a,'#R#')){
			list($r) = explode('/',$a,2);
			$a = substr($a,strlen($r));
			$b = str_replace('#T#','',$b);
		}
		$al = preg_split("/\//",$a,-1,PREG_SPLIT_NO_EMPTY);
		$bl = preg_split("/\//",$b,-1,PREG_SPLIT_NO_EMPTY);
		
		for($i=0;$i<sizeof($al)-substr_count($b,'../');$i++){
			if($al[$i] != '.' && $al[$i] != '..') $d .= $al[$i].'/';
		}
		for($i=0;$i<sizeof($bl);$i++){
			if($bl[$i] != '.' && $bl[$i] != '..') $t .= '/'.$bl[$i];
		}
		$t = (!empty($d)) ? substr($t,1) : $t;
		$d = (!empty($d) && $d[0] != '/' && substr($d,0,3) != '#T#' && !strpos($d,'#W#')) ? '/'.$d : $d;
		return str_replace($p[4],$p[5],$r.$d.$t);
	}
	/**
	 * パスの前後にスラッシュを追加／削除を行う
	 */
	public static function path_slash(string $path, ?bool $prefix, ?bool $postfix=null): string{
		if($path == '/') return ($postfix === true) ? '/' : '';
		if(!empty($path)){
			if($prefix === true){
				if($path[0] != '/') $path = '/'.$path;
			}else if($prefix === false){
				if($path[0] == '/') $path = substr($path,1);
			}
			if($postfix === true){
				if(substr($path,-1) != '/') $path = $path.'/';
			}else if($postfix === false){
				if(substr($path,-1) == '/') $path = substr($path,0,-1);
			}
		}
		return $path;
	}
	/**
	 * 複数プロセスで処理する
	 * dataの数だけプロセスをフォークします
	 * @param callable $callback 処理する関数
	 * @param array $data 処理対象のデータ
	 */
	public static function pctrl(callable $callback, array $data): void{
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
	public static function exit_error(): void{
		exit(1);
	}
	/**
	 * 一時停止として終了する
	 */
	public static function exit_wait(): void{
		exit(19);
	}
}
