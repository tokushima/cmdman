<?php
namespace cmdman;

class Command{
	private static ?array $include_path_cache = null;

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

						if(str_ends_with($nsp,'/')){
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
	private static function valid_dir(string $dir): bool{
		return is_dir($dir) &&
			ctype_upper(basename($dir)[0]) &&
			!str_contains($dir,'/.') &&
			!str_contains($dir,'/_');
	}
	private static function get_file(string $command): string{
		$protocol = '';

		if(str_contains($command,'#')){
			[$file,$command] = explode('#',$command,2);
			$file = realpath($file);
			$protocol = 'phar://';
		}
		if(str_contains($command,'::')){
			[$command,$func] = explode('::',$command,2);

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

	public static function exec(string $command, mixed $error_funcs=null): void{
		try{
			$__cmdman_file = self::get_file($command);

			if(str_starts_with($__cmdman_file,'phar://')){
				include_once(preg_replace('/^phar:\/\/(.+\.phar)\/.+$/','\\1',$__cmdman_file));
			}
			if(is_file($f=dirname($__cmdman_file).'/__setup__.php')){
				include($f);
			}

			foreach(self::get_params($command, $__cmdman_file) as $__cmdman_key => $__cmdman_param){
				$__cmdman_values = [];
				$__cmdman_err = new \InvalidArgumentException('$'.$__cmdman_key.' must be an `'.$__cmdman_param[0].'`');
				$__cmdman_opts = \cmdman\Args::opts($__cmdman_key);

				if(empty($__cmdman_opts)){
					if($__cmdman_param[2]['require']){
						throw new \InvalidArgumentException('--'.$__cmdman_key.' required');
					}
					if(isset($__cmdman_param[2]['init'])){
						$__cmdman_opts = is_array($__cmdman_param[2]['init']) ? $__cmdman_param[2]['init'] : [$__cmdman_param[2]['init']];
					}
				}
				foreach($__cmdman_opts as $__cmdman_val){
					$__cmdman_type = $__cmdman_param[2]['is_a'] ? substr($__cmdman_param[0],0,-2) : $__cmdman_param[0];
					$__cmdman_val = match($__cmdman_type){
						'string' => is_string($__cmdman_val) ? $__cmdman_val : throw $__cmdman_err,
						'int','integer' => (!is_numeric($__cmdman_val) || !ctype_digit((string)$__cmdman_val)) ? throw $__cmdman_err : (int)$__cmdman_val,
						'float' => !is_numeric($__cmdman_val) ? throw $__cmdman_err : (float)$__cmdman_val,
						'bool','boolean' => match(true){
							$__cmdman_val === 'true', $__cmdman_val === true => true,
							$__cmdman_val === 'false', $__cmdman_val === false => false,
							default => throw $__cmdman_err,
						},
						default => $__cmdman_val,
					};
					$__cmdman_values[] = $__cmdman_val;
				}
				$$__cmdman_key = ($__cmdman_param[2]['is_a'] ? $__cmdman_values : (empty($__cmdman_values) ? null : $__cmdman_values[0]));
			}
			include($__cmdman_file);

			if(is_file($f=dirname($__cmdman_file).'/__teardown__.php')){
				include($f);
			}
		}catch(\cmdman\CommandNotFound $e){
			\cmdman\Std::println_danger(PHP_EOL.$e->getMessage());
			\cmdman\Std::println();
		}catch(\Exception $exception){
			if(is_file($__cmdman_file) && is_file($f=dirname($__cmdman_file).'/__exception__.php')){
				include($f);
			}
			\cmdman\Std::println_danger(PHP_EOL.'Exception: ('.$exception::class.')');
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
	private static function error_callback(mixed $error_funcs, \Throwable $exception): never{
		if(!is_callable($error_funcs) && defined('CMDMAN_ERROR_CALLBACK')){
			$error_funcs = constant('CMDMAN_ERROR_CALLBACK');
		}
		if(is_string($error_funcs)){
			if(str_contains($error_funcs,'::')){
				$error_funcs = explode('::',$error_funcs);
				if(str_contains($error_funcs[0],'.')){
					$error_funcs[0] = '\\'.str_replace('.','\\',$error_funcs[0]);
				}
			}
		}
		if(is_callable($error_funcs)){
			$error_funcs($exception);
		}
		\cmdman\Util::exit_error();
	}
	private static function parse_annotation(string $str): ?array{
		$str = trim($str,'[] ');
		if(empty($str)){
			return [];
		}
		$result = [];
		if(preg_match_all("/'(\w+)'\s*=>\s*(.+?)(?:\s*,\s*|$)/", $str, $matches, PREG_SET_ORDER)){
			foreach($matches as $m){
				$val = trim($m[2]);
				$result[$m[1]] = match(true){
					$val === 'true' => true,
					$val === 'false' => false,
					is_numeric($val) => str_contains($val,'.') ? (float)$val : (int)$val,
					default => trim($val,"'\""),
				};
			}
			return $result;
		}
		return null;
	}
	private static function get_document(string $file): string{
		return (preg_match('/\/\*\*.+?\*\//s',file_get_contents($file),$m)) ?
		trim(preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(['/'.'**','*'.'/'],'',$m[0]))) :
		'';
	}
	private static function get_summary(string $file): string{
		$doc = trim(preg_replace('/@.+/','',self::get_document($file)));
		[$summary] = explode(PHP_EOL,$doc);
		return $summary;
	}
	private static function get_params(string $command, ?string $file=null): array{
		$doc = self::get_document($file ?? self::get_file($command));

		$help_params = [];
		if(preg_match_all('/@.+/',$doc,$as)){
			foreach($as[0] as $m){
				if(preg_match("/@(\w+)\s+([^\s]+)\s+\\$(\w+)(.*)/",$m,$p)){
					if($p[1] === 'param'){
						$help_params[$p[3]] = [$p[2],trim($p[4]),['is_a'=>false,'init'=>null,'require'=>false]];
					}
				}else if(preg_match("/@(\w+)\s+\\$(\w+)(.*)/",$m,$p)){
					$help_params[$p[2]] = ['string',trim($p[3]),['is_a'=>false,'init'=>null,'require'=>false]];
				}
			}
		}
		foreach($help_params as $k => $v){
			if(str_ends_with($v[0],'[]')){
				$help_params[$k][0] = substr($v[0],0,-2);
				$help_params[$k][2]['is_a'] = true;
			}
			match($help_params[$k][0]){
				'string','int','integer','float','bool','boolean' => null,
				default => throw new \InvalidArgumentException('$'.$k.': invalid type `'.$v[0].'`'),
			};
			if(false !== ($p=strpos($v[1],'@['))){
				$anon = self::parse_annotation(substr($v[1],$p+1,strrpos($v[1],']')-$p));
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


	public static function find_cmd(array &$list, \RecursiveDirectoryIterator|\Phar|string $r, ?string $realpath=null): void{
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
							!str_starts_with($fi->getFilename(),'_') &&
							str_ends_with($fi->getFilename(),'.php') &&
							!isset($list[$fi->getPathname()])
						){
							if(str_contains($f->getPathname(),'phar://')){
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
