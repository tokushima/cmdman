<?php
namespace cmdman;

class Cmd{
	/**
	 * PHPファイルの改行コードをCRに統一する
	 */
	public static function source_format($work){
		$count = 0;
		foreach(new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator(
						$work,
						\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::UNIX_PATHS
				),\RecursiveIteratorIterator::SELF_FIRST
		) as $f){
			if($f->isFile() && substr($f->getFilename(),-4) == '.php'){
				$src = file_get_contents($f->getPathname());
				$nsrc = str_replace(array("\r\n","\r","\n"),"\n",$src);
				$nsrc = preg_replace('/^(.+)[\040\t]+$/','\\1',$nsrc);
				
				if($src != $nsrc){
					file_put_contents($f->getPathname(),$nsrc);
					print(' '.$f->getPathname().PHP_EOL);
					$count++;
				}
			}
		}
		print('trimming: '.$count.PHP_EOL);
		
	}
	

	/**
	 * ライブラリをpharにする
	 * @param string $src ライブラリのルートフォルダ @['require'=>true]
	 * @param string $out 出力先ファイル名
	 */
	public static function phar($src,$out=null){
		ini_set('memory_limit',-1);
		
		if(empty($src) || ($src = realpath($src)) === false || !is_dir($src)){
			throw new \InvalidArgumentException('Not a directory');
		}
		$src = $src.'/';
		$ns = '';
		$mkdir = array();
		$files = array();

		$srclen = strlen($src);
		foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
				$src,
				\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::UNIX_PATHS
		),\RecursiveIteratorIterator::SELF_FIRST) as $r){
			if($r->isFile()){
				if(empty($ns)){
					$ns = str_replace($src,'',dirname($r->getPathname()));
				}
				$path = substr($r,$srclen);
				$dir = dirname($path);
		
				if($dir != '.'){
					$d = explode('/',$dir);
		
					while(!empty($d)){
						$dp = implode('/',$d);
		
						if(isset($mkdir[$dp])){
							break;
						}
						$mkdir[$dp] = $dp;
						array_shift($d);
					}
				}
				$files[$path] = $r->getPathname();
			}
		}
		ksort($mkdir);

		$output = empty($out) ? basename($ns).'.phar' : $out;
		
		if(substr($output,- 5) != '.phar'){
			$output = $output.'.phar';
		}
		if(is_file($output)){
			unlink($output);
		}
		\cmdman\Util::mkdir(dirname($output));

		try{
			$phar = new \Phar($output,0,basename($output));

			foreach($mkdir as $m){
				$phar->addEmptyDir('src/'.$m);
			}
			foreach($files as $k => $v){
				$phar->addFile($v,'src/'.$k);
			}
		
			$stabstr = sprintf("Phar::mapPhar('%s');",basename($output));
			$stabstr .= sprintf(<<< 'STAB'
		spl_autoload_register(function($c){
			$c = str_replace('\\','/',$c);
		
			if(strpos($c,'%s/') === 0 && is_file($f='phar://'.__FILE__.'/src/'.$c.'.php')){
				require_once($f);
			}
			return false;
		},true,false);
STAB
					,$ns);

			if(is_file('autoload.php')){
				$phar->addFile('autoload.php','autoload.php');
		
				$stabstr .= sprintf(PHP_EOL
						."require_once('phar://%s/autoload.php');"
						,basename($output)
				);
			}
			$stabstr .= sprintf(PHP_EOL.<<< 'STAB'
		$dir = getcwd().'/lib';
		if(is_dir($dir) && strpos(get_include_path(),$dir) === false){
			set_include_path($dir.PATH_SEPARATOR.get_include_path());
		}
STAB
			);
			
			if(is_file('main.php')){
				$phar->addFile('main.php','main.php');
				
				$stabstr .= sprintf(PHP_EOL
						."require_once('phar://%s/main.php');"
						,basename($output)
				);
			}
			
			$phar->setStub(sprintf(<<< 'STAB'
<?php
	%s
	__HALT_COMPILER();
?>
STAB
					,$stabstr));
			
			$phar->addFromString('version',date('Ymd.His'));
			
			try{
				$phar->compressFiles(\Phar::GZ);
			}catch(\BadMethodCallException $e){
			}
		
			if(is_file($output)){
				\cmdman\Std::println_info('Created '.$output.' ['.filesize($output).' byte]');
			}else{
				\cmdman\Std::println_danger('Failed '.$output);
			}
		}catch(\UnexpectedValueException $e){
			\cmdman\Std::println_info($e->getMessage().PHP_EOL.'usage: php -d phar.readonly=0 cmdman.phar archive '.str_replace(getcwd().'/','',$src.' '.$out));
		}catch(\Exception $e){
			\cmdman\Std::println_danger(get_class($e).': '.$e->getMessage());
			\cmdman\Std::println_warning($e->getTraceAsString());
		}		
	}
	
	
	/**
	 * pharを展開する
	 * @param string $f 展開したいpharファイル @['require'=>true]
	 * @param string $o 出力先
	 */
	public static function unphar($f,$o=null){
		$f = empty($f) ? false : realpath($f);

		if($f === false){
			throw new \InvalidArgumentException('`'.$f.'` not foundf');
		}
		if(!empty($o) && substr($o,-1) == '/'){
			$o = substr($o,1);
		
			\cmdman\Util::mkdir($o);
			$o = realpath($o);
		}
		
		$it = new \RecursiveIteratorIterator(new \Phar($f),\RecursiveIteratorIterator::SELF_FIRST);
		
		foreach($it as $i){
			if($i->isFile()){
				$file = str_replace('phar://'.$f,'',$i->getPathname());
		
				if(!empty($o)){
					\cmdman\Util::file_write($o.$file,file_get_contents($i->getPathname()));
					\cmdman\Std::println_info('Written '.$o.$file);
				}else{
					\cmdman\Std::println(' '.$file);
				}
			}
		}
	}
}
