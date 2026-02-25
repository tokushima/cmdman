<?php
namespace cmdman;

class Command{
	private static $include_path_cache = null;

	public static function init(): void{
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
	}
	private static function get_include_path(): array{
		if(self::$include_path_cache !== null){
			return self::$include_path_cache;
		}
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
					foreach($prefixs as $ns => $nspath){
						$nsp = str_replace('\\','/',$ns);
						
						if(substr($nsp,-1) == '/'){
							$nsp = substr($nsp,0,-1);
						}
						foreach($nspath as $path){
							$path = realpath($path);

							if($path !== false){
								$path = str_replace('\\','/',$path);
								
								if(preg_match('/^(.+)\/'.preg_quote($nsp,'/').'$/',$path,$m)){
									$path = $m[1];
								}
								$include_path[$path] = true;
							}
						}
					}
				}
			}
		}
		$include_path[dirname(__DIR__)] = true;
		
		krsort($include_path);
		self::$include_path_cache = array_keys($include_path);
		return self::$include_path_cache;
	}
	private static function valid_dir($dir): bool{
		if(is_dir($dir) &&
				ctype_upper(substr(basename($dir),0,1)) &&
				strpos($dir,'/.') === false &&
				strpos($dir,'/_') === false
		){
			return true;
		}
		return false;
	}
	private static function get_file(string $command): string{
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
					if(self::valid_dir(dirname(dirname($f)))){
						return $f;
					}
				}
			}
		}else{
			foreach((isset($file) ? [$file] : self::get_include_path()) as $p){
				if(is_file($f=($protocol.$p.'/'.str_replace('.','/',$command).'/cmd.php')) ||
						is_file($f=($protocol.$p.'/src/'.str_replace('.','/',$command).'/cmd.php'))
				){
					if(self::valid_dir(dirname($f))){
						return $f;
					}
				}
			}
		}
		throw new \cmdman\CommandNotFound('command not found: '.$command);
	}

	public static function exec(string $command, $error_funcs=null): void{		
		try{
			$_execute_file_519904 = self::get_file($command);

			if(strpos($_execute_file_519904,'phar://') === 0){
				include_once(preg_replace('/^phar:\/\/(.+\.phar)\/.+$/','\\1',$_execute_file_519904));
			}
			if(is_file($f=dirname($_execute_file_519904).'/__setup__.php')){
				include($f);
			}
			
			foreach(self::get_params($command, $_execute_file_519904) as $_k_679243 => $_i_526477){
				$_value_824432 = [];
				$_emsg_407635 = new \InvalidArgumentException('$'.$_k_679243.' must be an `'.$_i_526477[0].'`');
				$_opts_944947 = \cmdman\Args::opts($_k_679243);

				if(empty($_opts_944947)){
					if($_i_526477[2]['require']){
						throw new \InvalidArgumentException('--'.$_k_679243.' required');
					}
					if(isset($_i_526477[2]['init'])){
						$_opts_944947 = is_array($_i_526477[2]['init']) ? $_i_526477[2]['init'] : [$_i_526477[2]['init']];
					}
				}
				foreach($_opts_944947 as $_v_795509){
					switch($_i_526477[2]['is_a'] ? substr($_i_526477[0],0,-2) : $_i_526477[0]){
						case 'string':
							if(!is_string($_v_795509)){
							    throw $_emsg_407635;
							}
							break;
						case 'int':
						case 'integer':
							if(!is_numeric($_v_795509) || !ctype_digit((string)$_v_795509)){
							    throw $_emsg_407635;
							}
							$_v_795509 = (int)$_v_795509;
							break;
						case 'float':
							if(!is_numeric($_v_795509)){
							    throw $_emsg_407635;
							}
							$_v_795509 = (float)$_v_795509;
							break;
						case 'bool':
						case 'boolean':
							if(is_string($_v_795509)){
								if($_v_795509 === 'true'){
								    $_v_795509 = true;
								}
								if($_v_795509 === 'false'){
								    $_v_795509 = false;
								}
							}
							if(!is_bool($_v_795509)){
							    throw $_emsg_407635;
							}
							$_v_795509 = (boolean)$_v_795509;
							break;
					}
					$_value_824432[] = $_v_795509;
				}
				$$_k_679243 = ($_i_526477[2]['is_a'] ? $_value_824432 : (empty($_value_824432) ? null : $_value_824432[0]));
			}
			include($_execute_file_519904);

			if(is_file($f=dirname($_execute_file_519904).'/__teardown__.php')){
				include($f);
			}
		}catch(\cmdman\CommandNotFound $e){
			\cmdman\Std::println_danger(PHP_EOL.$e->getMessage());
			\cmdman\Std::println();
		}catch(\Exception $exception){
			if(is_file($_execute_file_519904) && is_file($f=dirname($_execute_file_519904).'/__exception__.php')){
				include($f);
			}
			\cmdman\Std::println_danger(PHP_EOL.'Exception: ('.get_class($exception).')');
			\cmdman\Std::println(implode(' ',explode(PHP_EOL,PHP_EOL.$exception->getMessage())));
			\cmdman\Std::println();

			self::error_callback($error_funcs, $exception);
		}catch(\Error $exception){
			\cmdman\Std::println_danger(PHP_EOL.'Fatal: ');
			\cmdman\Std::println(implode(' ',explode(PHP_EOL,PHP_EOL.$exception->getMessage())));
			\cmdman\Std::println();
			
			self::error_callback($error_funcs, $exception);
		}
	}
	private static function error_callback($error_funcs,$exception){
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
		\cmdman\Util::exit_error();
	}
	private static function get_document($file){
		return (preg_match('/\/\*\*.+?\*\//s',file_get_contents($file),$m)) ?
		trim(preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(['/'.'**','*'.'/'],'',$m[0]))) :
		'';
	}
	private static function get_summary($file){
		$doc = trim(preg_replace('/@.+/','',self::get_document($file)));
		list($summary) = explode(PHP_EOL,$doc);
		return $summary;
	}
	private static function get_params($command, $file=null){
		$doc = self::get_document($file ?? self::get_file($command));
			
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
				case 'int':
				case 'integer':
				case 'float':
				case 'bool':
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

	public static function doc(string $command): void{
		try{
			$file = self::get_file($command);
			$pad = 4;
			$help_params = self::get_params($command, $file);
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
			$doc = self::get_document($file);
			$doc = trim(preg_replace('/@.+/','',$doc));
			\cmdman\Std::println("\n  Description:");
			\cmdman\Std::println('   '.str_replace("\n","\n  ",$doc)."\n");
		}catch(\cmdman\CommandNotFound $e){
			\cmdman\Std::println_danger(PHP_EOL.$e->getMessage());
			\cmdman\Std::println();
		}
	}


	public static function find_cmd(&$list, $r, $realpath=null): void{
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
			if(self::valid_dir($f->getPathname())){
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

	public static function get_list(): array{
		$list = [];
		foreach(self::get_include_path() as $p){
			if(($r = realpath($p)) !== false){
				self::find_cmd($list,$r);
			}
		}
		ksort($list);
		
		$cmdlist = [];
		self::find_cmd($cmdlist,dirname(__DIR__));
		
		foreach($cmdlist as $k => $v){
			$cmdlist[$k][0] = str_replace('#','',$cmdlist[$k][0]);
		}
		return array_merge($list,$cmdlist);
	}
}
