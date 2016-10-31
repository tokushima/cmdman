<?php
namespace cmdman;

class Command{
	/**
	 * init
	 */
	public static function init(){
		if(is_file($f=getcwd().'/bootstrap.php') ||
			is_file($f=getcwd().'/autoload.php') ||
			is_file($f=getcwd().'/vendor/autoload.php')
		){
			try{
				ob_start();
					include_once(realpath($f));
				ob_end_clean();
			}catch(\Exception $e){
			}
		}
		if(is_file($f=getcwd().'/ebi.phar')){
			try{
				ob_start();
					include_once(realpath($f));
				ob_end_clean();
			}catch(\Exception $e){
			}			
		}
	}
	private static function get_include_path(){
		$include_path = [];

		foreach(explode(PATH_SEPARATOR,get_include_path()) as $p){
			if(($rp = realpath($p)) !== false){
				$include_path[$rp] = true;
			}
		}
		if(is_dir($d=getcwd().'/lib')){
			$include_path[realpath($d)] = true;
		}
		if(class_exists('Composer\Autoload\ClassLoader')){
			$r = new \ReflectionClass('Composer\Autoload\ClassLoader');
			$vendor_dir = dirname(dirname($r->getFileName()));
				
			if(is_file($loader_php=$vendor_dir.'/autoload.php')){
				$loader = include($loader_php);
				
				foreach([$loader->getPrefixes(),$loader->getPrefixesPsr4()] as $prefixs){
					foreach($prefixs as $ns){
						foreach($ns as $path){
							$path = realpath($path);
							
							if($path !== false){
								$include_path[$path] = true;
							}
						}
					}
				}
			}
		}
		krsort($include_path);
		return array_keys($include_path);
	}
	private static function validdir($dir){
		if(is_dir($dir) &&
				ctype_upper(substr(basename($dir),0,1)) &&
				strpos($dir,'/.') === false &&
				strpos($dir,'/_') === false
		){
			return true;
		}
		return false;
	}
	private static function get_file($command){
		$protocol = '';
		
		if(strpos($command,'#') !== false){
			list($file,$command) = explode('#',$command,2);
			$file = realpath($file);
			$protocol = 'phar://';
		}		
		if(strpos($command,'::') !== false){
			list($command,$func) = explode('::',$command,2);
			
			foreach((isset($file) ? [$file] : self::get_include_path()) as $p){
				if(is_file($f=($protocol.$p.'/'.str_replace('.','/',$command).'/cmd/'.$func.'.php')) ||
					is_file($f=($protocol.$p.'/src/'.str_replace('.','/',$command).'/cmd/'.$func.'.php'))
				){
					if(self::validdir(dirname(dirname($f)))){
						return $f;
					}
				}
			}
		}else{
			foreach((isset($file) ? [$file] : self::get_include_path()) as $p){
				if(is_file($f=($protocol.$p.'/'.str_replace('.','/',$command).'/cmd.php')) ||
						is_file($f=($protocol.$p.'/src/'.str_replace('.','/',$command).'/cmd.php'))
				){
					if(self::validdir(dirname($f))){
						return $f;
					}
				}
			}
		}
		throw new \cmdman\Notfound($command.' not found.');
	}
	/**
	 * exec
	 * @param string $command
	 * @param callable $error_funcs
	 * @throws \InvalidArgumentException
	 */
	public static function exec($command,$error_funcs=null){
		$_execute_file = self::get_file($command);
		try{
			if(strpos($_execute_file,'phar://') === 0){
				include_once(preg_replace('/^phar:\/\/(.+\.phar)\/.+$/','\\1',$_execute_file));
			}
			if(is_file($f=dirname($_execute_file).'/__setup__.php')){
				include($f);
			}
			$arg = \cmdman\Args::value();
			$args = \cmdman\Args::values();

			foreach(self::get_params($command) as $k => $i){
				$value = [];
				$emsg = new \InvalidArgumentException('$'.$k.' must be an `'.$i[0].'`');
				$opts = \cmdman\Args::opts($k);

				if(empty($opts)){
					if($i[2]['require']){
						throw new \InvalidArgumentException('--'.$k.' required');
					}
					if(isset($i[2]['init'])){
						$opts = is_array($i[2]['init']) ? $i[2]['init'] : [$i[2]['init']];
					}
				}
				foreach($opts as $v){
					switch($i[2]['is_a'] ? substr($i[0],0,-2) : $i[0]){
						case 'string':
							if(!is_string($v)) throw $emsg;
							break;
						case 'integer':
							if(!is_numeric($v) || !ctype_digit((string)$v)) throw $emsg;
							$v = (int)$v;
							break;
						case 'float':
							if(!is_numeric($v)) throw $emsg;
							$v = (float)$v;
							break;
						case 'boolean':
							if(is_string($v)){
								if($v === 'true') $v = true;
								if($v === 'false') $v = false;
							}
							if(!is_bool($v)) throw $emsg;
							$v = (boolean)$v;
							break;
					}
					$value[] = $v;
				}
				$$k = ($i[2]['is_a'] ? $value : (empty($value) ? null : $value[0]));
			}
			include($_execute_file);

			if(is_file($f=dirname($_execute_file).'/__teardown__.php')){
				include($f);
			}
		}catch(\Exception $exception){
			if(is_file($_execute_file) && is_file($f=dirname($_execute_file).'/__exception__.php')){
				include($f);
			}
			\cmdman\Std::println_danger(PHP_EOL.'Exception: ('.get_class($exception).')');
			\cmdman\Std::println(implode(' ',explode(PHP_EOL,PHP_EOL.$exception->getMessage())));
			\cmdman\Std::println();

			if(!is_callable($error_funcs) && defined('CMDMAN_ERROR_CALLBACK')){
				$error_funcs = constant('CMDMAN_ERROR_CALLBACK');
			}
			if(is_string($error_funcs)){
				if(strpos($error_funcs,'::') !== false){
					$error_funcs = explode('::',$error_funcs);
					if(strpos($error_funcs[0],'.') !== false){
						$error_funcs[0] = '\\'.str_replace('.','\\',$error_funcs[0]);
					}
				}
			}
			if(is_callable($error_funcs)){
				call_user_func_array($error_funcs,[$exception]);
			}
		}
	}
	private static function get_docuemnt($file){
		return (preg_match('/\/\*\*.+?\*\//s',file_get_contents($file),$m)) ?
		trim(preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(['/'.'**','*'.'/'],'',$m[0]))) :
		'';
	}
	private static function get_summary($file){
		$doc = trim(preg_replace('/@.+/','',self::get_docuemnt($file)));
		list($summary) = explode(PHP_EOL,$doc);
		return $summary;
	}
	private static function get_params($command){
		$doc = self::get_docuemnt(self::get_file($command));
			
		$help_params = [];
		if(preg_match_all('/@.+/',$doc,$as)){
			foreach($as[0] as $m){
				if(preg_match("/@(\w+)\s+([^\s]+)\s+\\$(\w+)(.*)/",$m,$p)){
					if($p[1] == 'param'){
						$help_params[$p[3]] = [$p[2],trim($p[4]),['is_a'=>false,'init'=>null,'require'=>false]];
					}
				}else if(preg_match("/@(\w+)\s+\\$(\w+)(.*)/",$m,$p)){
					$help_params[$p[2]] = ['string',trim($p[3]),['is_a'=>false,'init'=>null,'require'=>false]];
				}
			}
		}
		foreach($help_params as $k => $v){
			if(substr($v[0],-2) == '[]'){
				$help_params[$k][0] = substr($v[0],0,-2);
				$help_params[$k][2]['is_a'] = true;
			}
			switch($help_params[$k][0]){
				case 'string':
				case 'integer':
				case 'float':
				case 'boolean':
					break;
				default:
					throw new \InvalidArgumentException('$'.$k.': invalid type `'.$v[0].'`');
			}
			if(false !== ($p=strpos($v[1],'@['))){
				$anon = @eval('return '.str_replace(['[',']'],['array(',')'],substr($v[1],$p+1,strrpos($v[1],']')-$p).';'));
				if(!is_array($anon)){
					throw new \InvalidArgumentException('annotation error : `'.$k.'`');
				}
				foreach(['init','require'] as $a){
					if(isset($anon[$a])){
						$help_params[$k][2][$a] = $anon[$a];
					}
				}
				$help_params[$k][1] = ($p > 0) ? substr($v[1],0,$p-1) : '';
			}
		}
		return $help_params;
	}
	/**
	 * ge document
	 * @param string $command
	 */
	public static function doc($command){
		$pad = 4;
		$help_params = self::get_params($command);
		foreach(array_keys($help_params) as $k){
			if($pad < strlen($k)){
				$pad = strlen($k);
			}
		}
		\cmdman\Std::println(PHP_EOL.'Usage:');
		\cmdman\Std::println('  '.$command);
		if(!empty($help_params)){
			\cmdman\Std::println("\n  Options:");
			foreach($help_params as $k => $v){
				\cmdman\Std::println('   '.sprintf('--%s%s %s',str_pad($k,$pad),(empty($v[0]) ? '' : ' ('.$v[0].')'),trim($v[1])));
			}
		}
		$doc = self::get_docuemnt(self::get_file($command));
		$doc = trim(preg_replace('/@.+/','',$doc));
		\cmdman\Std::println("\n  Description:");
		\cmdman\Std::println('   '.str_replace("\n","\n  ",$doc)."\n");
	}
	/**
	 * finding commnads
	 * @param mixed $list
	 * @param string $r
	 * @param string $realpath
	 */
	public static function find_cmd(&$list,$r,$realpath=null){
		if($r instanceof \RecursiveDirectoryIterator){
			$it = $r;
			$r = $r->getFilename();
		}else{
			$it = new \RecursiveDirectoryIterator($r,
					\FilesystemIterator::CURRENT_AS_FILEINFO|\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::UNIX_PATHS
			);
		}
		$it = new \RecursiveIteratorIterator($it
				,\RecursiveIteratorIterator::SELF_FIRST
		);
		foreach($it as $f){
			if(self::validdir($f->getPathname())){
				if(is_file($cf=$f->getPathname().'/cmd.php') && !isset($list[$cf])){
					$class = str_replace('/','.',substr(dirname($cf),strlen($r)+1));
					$list[$cf] = [$class,self::get_summary($cf)];
				}
				if(is_dir($cd=$f->getPathname().'/cmd/')){
					foreach(new \DirectoryIterator($cd) as $fi){
						if(
							$fi->isFile() &&
							strpos($fi->getFilename(),'_') !== 0 &&
							substr($fi->getFilename(),-4) == '.php' &&
							!isset($list[$fi->getPathname()] )
						){
							if(strpos($f->getPathname(),'phar://') !== false){
								$class = $realpath.'#'.
									preg_replace(
											'/^(src.)/',
											'',
											str_replace('/','.',substr($f->getPathname(),strpos($f->getPathname(),'.phar/')+6))
									);
							}else{
								$class = str_replace('/','.',substr($f->getPathname(),strlen($r)+1));
							}
							$list[$fi->getPathname()] = [
								$class.'::'.substr($fi->getFilename(),0,-4),
								self::get_summary($fi->getPathname())
							];
						}
					}
				}
			}
		}
	}
	/**
	 * get file list
	 * @return string{}
	 */
	public static function get_list(){
		$list = [];
		foreach(self::get_include_path() as $p){
			if(($r = realpath($p)) !== false){
				self::find_cmd($list,$r);
			}
		}
		ksort($list);
		return $list;
	}
}
