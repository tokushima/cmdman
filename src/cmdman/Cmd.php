<?php
namespace cmdman;

class Cmd{
	/**
	 * ebi download
	 */
	public static function download_ebi(){
		file_put_contents('ebi.phar',file_get_contents('http://git.io/ebi.phar'));
		
		if(is_file('ebi.phar')){
			\cmdman\Std::println_success('ebi successfully installed to: '.realpath('ebi.phar'));
		}
	}
	
	/**
	 * composer download
	 */
	public static function download_composer(){
		$composer_json = <<< _JSON_
{
	"config":{
		"preferred-install": "dist"
	},
	"require": {
		"tokushima/ebi": "dev-master"
	}
}
_JSON_;
		
		if(!is_file('composer.json')){
			file_put_contents('composer.json',$composer_json);
			\cmdman\Std::println_success('Written: '.realpath('composer.json'));
		}
		eval('?>'.file_get_contents('https://getcomposer.org/installer'));
	}

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
	 */
	public static function phar($src){
		if($src === false || is_file($src)){
			throw new \InvalidArgumentException($src.' not found');
		}
		$src = realpath($src).'/';
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

		$output = $ns.'.phar';
		if(is_file($output)){
			unlink($output);
		}
		\ebi\Util::mkdir(dirname($output));

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
				$stabstr = $stabstr.PHP_EOL.str_replace(array('<?php','?>'),'',file_get_contents('main.php'));
			}
			
			$phar->setStub(sprintf(<<< 'STAB'
<?php
	%s
	__HALT_COMPILER();
?>
STAB
					,$stabstr));
			$phar->compressFiles(\Phar::GZ);
		
			if(is_file($output)){
				\cmdman\Std::println_info('Created '.$output.' ['.filesize($output).' byte]');
			}else{
				\cmdman\Std::println_danger('Failed '.$output);
			}
		}catch(\UnexpectedValueException $e){
			\cmdman\Std::println_info($e->getMessage().PHP_EOL.'usage: php -d phar.readonly=0 cmdman.phar archive '.str_replace(getcwd().'/','',$src));
		}catch(\Exception $e){
			\cmdman\Std::println_danger($e->getMessage());
			\cmdman\Std::println_warning($e->getTraceAsString());
		}
		
	}
	
	public static function unphar(){
		/**
		 * pharを展開する
		 * @param string $f 展開したいpharファイル @['require'=>true]
		 * @param string $o 出力先
		 */
		
		$f = realpath($f);
		
		if($f === false){
			throw new \InvalidArgumentException($f.' not foundf');
		}
		if(!empty($o) && substr($o,-1) == '/'){
			$o = substr($o,1);
		
			\ebi\Util::mkdir($o);
			$o = realpath($o);
		}
		
		$it = new \RecursiveIteratorIterator(new \Phar($f),\RecursiveIteratorIterator::SELF_FIRST);
		
		foreach($it as $i){
			if($i->isFile()){
				$file = str_replace('phar://'.$f,'',$i->getPathname());
		
				if(!empty($o)){
					\ebi\Util::file_write($o.$file,file_get_contents($i->getPathname()));
					\cmdman\Std::println_info('Written '.$o.$file);
				}else{
					\cmdman\Std::println(' '.$file);
				}
			}
		}
	}
}
