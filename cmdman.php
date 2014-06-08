<?php
/**
 * Command Line Framework
 * PHP 5 >= 5.3.0 
 * @author tokushima
 *
 */
namespace cmdman{
	/**
	 * parse $_SERVER['argv']
	 */
	class Args{
		static private $opt = array();
		static private $value = array();
		static private $cmd;
		
		static public function init(){
			$opt = $value = array();
			$argv = array_slice((isset($_SERVER['argv']) ? $_SERVER['argv'] : array()),1);
			
			if(!empty($argv) && isset($argv[0]) && substr($argv[0],0,1) != '-'){
				self::$cmd = array_shift($argv);
			}
			for($i=0;$i<sizeof($argv);$i++){
				if(substr($argv[$i],0,2) == '--'){
					$opt[substr($argv[$i],2)][] = ((isset($argv[$i+1]) && $argv[$i+1][0] != '-') ? $argv[++$i] : true);
				}else if(substr($argv[$i],0,1) == '-'){
					$n = substr($argv[$i],1);
					
					if(strlen($n) == 1){
						$opt[$argv[$i][1]][] = ((isset($argv[$i+1]) && $argv[$i+1][0] != '-') ? $argv[++$i] : true);
					}else{
						$opt[$n][] = ((isset($argv[$i+1]) && $argv[$i+1][0] != '-') ? $argv[++$i] : true);
						
						foreach(str_split($n,1) as $k){
							$opt[$k][] = true;
						}
					}
				}else{
					$value[] = $argv[$i];
				}
			}
			self::$opt = $opt;
			self::$value = $value;
		}
		static public function opt($name,$default=false){
			return array_key_exists($name,self::$opt) ? self::$opt[$name][0] : $default;
		}
		static public function value($default=null){
			return isset(self::$value[0]) ? self::$value[0] : $default;
		}
		static public function opts($name){
			return array_key_exists($name,self::$opt) ? self::$opt[$name] : array();
		}
		static public function values(){
			return self::$value;
		}
		static public function cmd(){
			return self::$cmd;
		}
	}
	/**
	 * router with built-in web server
	 */
	class Router{
		static private function log($state,$file,$uri){
			$error_log = ini_get('error_log');

			$data = array(
					date('Y-m-d H:i:s'),
					$state,
					(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''),
					$file,
					(isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : ''),
					(is_file($error_log) ? filesize($error_log) : 0),
					$uri,
			);
			file_put_contents('php://stdout',implode("\t",$data).PHP_EOL);
		}
		static private function include_php($filename,$subdir){
			if(isset($_SERVER['SERVER_NAME']) && isset($_SERVER['SERVER_PORT'])){
				$_ENV['APP_URL'] = 'http://'.$_SERVER['SERVER_NAME'].(($_SERVER['SERVER_PORT'] != 80) ? (':'.$_SERVER['SERVER_PORT']) : '').$subdir;
			}
			include($filename);
		}
		static public function start(){
			$dir = getcwd();
			$subdir = '';
			$uri = $_SERVER['REQUEST_URI'];
			if(strpos($uri,'?') !== false) list($uri) = explode('?',$uri,2);
			if(substr($uri,0,1) == '/'){
				$uri = substr($uri,1);
			}
			$uri_exp = explode('/',$uri,2);
			if(is_dir($uri_exp[0])){
				$subdir = '/'.$uri_exp[0];
				$dir = $dir.$subdir;
					
				if(isset($uri_exp[1])){
					$uri_exp = explode('/',$uri_exp[1],2);
				}
			}
			chdir($dir);
			if(is_file($f=($dir.'/'.implode('/',$uri_exp)))){
				self::log('success',$f,$uri);
				$info = pathinfo($f);
				if(isset($info['extension']) && strtolower($info['extension']) == 'php'){
					self::include_php($f,$subdir);
				}else{
					$mime = function($filename){
						$ext = (false !== ($p = strrpos($filename,'.'))) ? strtolower(substr($filename,$p+1)) : null;
						switch($ext){
							case 'jpg':
							case 'jpeg': return 'jpeg';
							case 'png':
							case 'gif':
							case 'bmp':
							case 'tiff': return 'image/'.$ext;
							case 'css': return 'text/css';
							case 'txt': return 'text/plain';
							case 'html': return 'text/html';
							case 'xml': return 'application/xml';
							case 'js': return 'text/javascript';
							case 'flv':
							case 'swf': return 'application/x-shockwave-flash';
							case '3gp': return 'video/3gpp';
							case 'gz':
							case 'tgz':
							case 'tar':
							case 'gz': return 'application/x-compress';
							case 'csv': return 'text/csv';
							case null:
							default:
								return 'application/octet-stream';
						}
					};
					header('Content-Type: '.$mime($f));
					readfile($f);
				}
			}else if(is_file($f=($dir.'/'.$uri_exp[0])) || is_file($f=($dir.'/'.$uri_exp[0].'.php'))){
				$_SERVER['PATH_INFO'] = '/'.(isset($uri_exp[1]) ? $uri_exp[1] : '');
				self::log('success',$f,$uri);
					
				self::include_php($f,$subdir,$uri);
			}else if(is_file($f=($dir.'/index.php'))){
				$_SERVER['PATH_INFO'] = '/'.implode('/',$uri_exp);
					
				self::log('success',$f);
				self::include_php($f,$subdir,$uri);
			}else{
				header('HTTP/1.1 404 Not Found');
				self::log('failure','',$uri);
			}
		}
	}
	/**
	 * command
	 */
	class Command{
		static public function init(){
			if(is_file($f=getcwd().'/bootstrap.php') || is_file($f=getcwd().'/vendor/autoload.php')){
				try{
					ob_start();
					include_once(realpath($f));
					ob_end_clean();
				}catch(\Exception $e){
				}
			}
		}
		static private function get_include_path(){
			$include_path = array();

			foreach(explode(PATH_SEPARATOR,get_include_path()) as $p){
				if(($rp = realpath($p)) !== false){
					$include_path[$rp] = true;
				}
			}			
			if(is_dir($d=__DIR__.'/lib')){
				$include_path[realpath($d)] = true;
			}
			if(class_exists('Composer\Autoload\ClassLoader')){
				$r = new \ReflectionClass('Composer\Autoload\ClassLoader');
				$composer_dir = dirname($r->getFileName());
				
				if(is_file($bf=realpath(dirname($composer_dir).'/autoload.php'))){
					ob_start();
						include_once($bf);
						if(is_file($composer_dir.'/autoload_namespaces.php')){
							$class_loader = include($bf);

							foreach($class_loader->getPrefixes() as $v){
								foreach($v as $p){
									if(($rp = realpath($p)) !== false){
										$include_path[$rp] = true;
									}
								}
							}
						}
					ob_end_clean();
				}
			}
			krsort($include_path);
			return array_keys($include_path);
		}
		static private function get_file($command){
			if(strpos($command,'::') !== false){
				list($command,$func) = explode('::',$command,2);
					
				foreach(self::get_include_path() as $p){
					if(is_file($f=($p.'/'.str_replace('.','/',$command).'/cmd/'.$func.'.php'))){
						return $f;
					}
				}
			}else{
				foreach(self::get_include_path() as $p){
					if(is_file($f=($p.'/'.str_replace('.','/',$command).'/cmd.php'))){
						return $f;
					}
				}
			}
			throw new \InvalidArgumentException($command.' not found.');
		}
		static public function exec($command,$error_funcs=null){
			$file = null;
			try{
				$file = self::get_file($command);
				if(is_file($f=dirname($file).'/__setup__.php')){
					include($f);
				}
				$arg = \cmdman\Args::value();
				$args = \cmdman\Args::values();
				
				foreach(self::get_params($command) as $k => $i){
					$value = array();
					$emsg = new \InvalidArgumentException('$'.$k.' must be an `'.$i[0].'`');
					$opts = \cmdman\Args::opts($k);

					if(empty($opts)){
						if($i[2]['require']){
							throw new \InvalidArgumentException('--'.$k.' required');
						}
						if(isset($i[2]['init'])){
							$opts = is_array($i[2]['init']) ? $i[2]['init'] : array($i[2]['init']);
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
								if(!is_bool($v)) throw $emsg;
								$v = (boolean)$v;
								break;
						}
						$value[] = $v;
					}
					$$k = ($i[2]['is_a'] ? $value : (empty($value) ? null : $value[0]));
				}
				include(self::get_file($command));
				
				if(is_file($f=dirname($file).'/__teardown__.php')){
					include($f);
				}
			}catch(\Exception $exception){
				if(is_file($file) && is_file($f=dirname($file).'/__exception__.php')){
					include($f);
				}
				\cmdman\Std::println_danger(PHP_EOL.'Exception: ');
				\cmdman\Std::println(implode(' ',explode(PHP_EOL,PHP_EOL.$e->getMessage())));
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
					call_user_func_array($error_funcs,array($e));
				}
			}
		}
		static private function get_docuemnt($file){
			return (preg_match('/\/\*\*.+?\*\//s',file_get_contents($file),$m)) ?
				trim(preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array('/'.'**','*'.'/'),'',$m[0]))) :
				'';
		}
		static private function get_summary($file){
			$doc = trim(preg_replace('/@.+/','',self::get_docuemnt($file)));
			list($summary) = explode(PHP_EOL,$doc);
			return $summary;
		}
		static private function get_params($command){
			$doc = self::get_docuemnt(self::get_file($command));
			
			$help_params = array();
			if(preg_match_all('/@.+/',$doc,$as)){
				foreach($as[0] as $m){
					if(preg_match("/@(\w+)\s+([^\s]+)\s+\\$(\w+)(.*)/",$m,$p)){
						if($p[1] == 'param'){
							$help_params[$p[3]] = array($p[2],trim($p[4]),array('is_a'=>false,'init'=>null,'require'=>false));
						}
					}else if(preg_match("/@(\w+)\s+\\$(\w+)(.*)/",$m,$p)){
						$help_params[$p[2]] = array('string',trim($p[3]),array('is_a'=>false,'init'=>null,'require'=>false));
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
					$anon = @eval('return '.str_replace(array('[',']'),array('array(',')'),substr($v[1],$p+1,strrpos($v[1],']')-$p).';'));
					if(!is_array($anon)){
						throw new \InvalidArgumentException('annotation error : `'.$k.'`');
					}
					foreach(array('init','require') as $a){
						if(isset($anon[$a])){
							$help_params[$k][2][$a] = $anon[$a];
						}
					}
					$help_params[$k][1] = ($p > 0) ? substr($v[1],0,$p-1) : '';
				}
			}
			return $help_params;
		}
		static public function doc($command){		
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
					\cmdman\Std::println('    '.sprintf('--%s%s %s',str_pad($k,$pad),(empty($v[0]) ? '' : ' ('.$v[0].')'),trim($v[1])));
				}
			}
			$doc = self::get_docuemnt(self::get_file($command));
			$doc = trim(preg_replace('/@.+/','',$doc));
			\cmdman\Std::println("\n  Description:");
			\cmdman\Std::println('    '.str_replace("\n","\n    ",$doc)."\n");
		}
		static public function get_list(){
			$list = array();
			
			foreach(self::get_include_path() as $p){
				if(($r = realpath($p)) !== false){
					foreach(new \RecursiveIteratorIterator(
							new \RecursiveDirectoryIterator($r,\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::UNIX_PATHS)
							,\RecursiveIteratorIterator::SELF_FIRST
					) as $f){
						if(
								$f->isDir() &&
								ctype_upper(substr($f->getFilename(),0,1)) &&
								strpos($f->getPathname(),'/.') === false &&
								strpos($f->getFilename(),'_') !== 0
						){
							if(is_file($cf=$f->getPathname().'/cmd.php') && !isset($list[$cf])){
								$class = str_replace('/','.',substr(dirname($cf),strlen($r)+1));
								$list[$cf] = array($class,self::get_summary($cf));
							}
							if(is_dir($cd=$f->getPathname().'/cmd/')){
								foreach(new \DirectoryIterator($cd) as $fi){
									if(
										$fi->isFile() &&
										strpos($fi->getFilename(),'_') !== 0 &&
										substr($fi->getFilename(),-4) == '.php' &&
										!isset($list[$fi->getPathname()] )
									){
										$class = str_replace('/','.',substr($f->getPathname(),strlen($r)+1));
										$list[$fi->getPathname()] = array($class.'::'.substr($fi->getFilename(),0,-4),self::get_summary($fi->getPathname()));
									}
								}
							}
						}
					}
				}
			}
			return $list;
		}
	}
	/**
	 * std
	 */
	class Std{
		/**
		 * 標準入力からの入力を取得する
		 * @param string $msg 入力待ちのメッセージ
		 * @param string $default 入力が空だった場合のデフォルト値
		 * @param string[] $choice 入力を選択式で求める
		 * @param boolean $multiline 複数行の入力をまつ、終了は行頭.(ドット)
		 * @param boolean $silently 入力を非表示にする(Windowsでは非表示になりません)
		 * @return string
		 */
		static public function read($msg,$default=null,$choice=array(),$multiline=false,$silently=false){
			while(true){
				$result = $b = null;
				print($msg.(empty($choice) ? '' : ' ('.implode(' / ',$choice).')').(empty($default) ? '' : ' ['.$default.']').': ');
				if($silently && substr(PHP_OS,0,3) != 'WIN') `tty -s && stty -echo`;
				while(true){
					fscanf(STDIN,'%s',$b);
					if($multiline && $b == '.') break;
					$result .= $b."\n";
					if(!$multiline) break;
				}
				if($silently && substr(PHP_OS,0,3) != 'WIN') `tty -s && stty echo`;
				$result = substr(str_replace(array("\r\n","\r","\n"),"\n",$result),0,-1);
				if(empty($result)) $result = $default;
				if(empty($choice) || in_array($result,$choice)) return $result;
			}
		}
		/**
		 * readのエイリアス、入力を非表示にする
		 * Windowsでは非表示になりません
		 * @param string $msg 入力待ちのメッセージ
		 * @param string $default 入力が空だった場合のデフォルト値
		 * @param string[] $choice 入力を選択式で求める
		 * @param boolean $multiline 複数行の入力をまつ、終了は行頭.(ドット)
		 * @return string
		 */
		static public function silently($msg,$default=null,$choice=array(),$multiline=false){
			return self::read($msg,$default,$choice,$multiline,true);
		}
		/**
		 * 色付きでプリント
		 * @param string $msg
		 * @param string $color ANSI Colors
		 */
		static public function println($msg='',$color='0'){
			if(substr(PHP_OS,0,3) != 'WIN'){
				print("\033[".$color."m");
				print($msg.PHP_EOL);
				print("\033[0m");
			}else{
				print($msg.PHP_EOL);
			}
		}
		static public function println_default($msg){
			self::println($msg,'37');
		}
		static public function println_primary($msg){
			self::println($msg,'34');
		}
		static public function println_success($msg){
			self::println($msg,'32');
		}
		static public function println_info($msg){
			self::println($msg,'36');
		}
		static public function println_warning($msg){
			self::println($msg,'33');
		}
		static public function println_danger($msg){
			self::println($msg,'31');
		}
	}
}
/**
 * cmdman PHP Command line tools
 * (cmdmanicipitidae)
 */
namespace{
	set_error_handler(function($n,$s,$f,$l){
		throw new \ErrorException($s,0,$n,$f,$l);
	});
	if(ini_get('date.timezone') == '') date_default_timezone_set('Asia/Tokyo');
	if(extension_loaded('mbstring')){
		if('neutral' == mb_language()) mb_language('Japanese');
		mb_internal_encoding('UTF-8');
	}
	if(isset($_SERVER['SERVER_PORT'])){
		header('HTTP/1.1 403 Forbidden');
		print('Forbidden');
		exit;
	}
	if(isset($_SERVER['SERVER_SOFTWARE']) && preg_match('/PHP.+Development Server/',$_SERVER['SERVER_SOFTWARE'])){
		\cmdman\Router::start();
		exit;
	}
	\cmdman\Args::init();
	\cmdman\Command::init();
	
	if(\cmdman\Args::cmd() == null){
		\cmdman\Std::println('cmdman 0.1.0 (PHP '.phpversion().')');
		$php = isset($_ENV['_']) ? $_ENV['_'] : 'php';
		\cmdman\Std::println_info(sprintf('Type \'%s %s subcommand --help\' for usage.'.PHP_EOL,basename($php),basename(__FILE__)));
		\cmdman\Std::println_primary('Subcommands:');
		
		$list = \cmdman\Command::get_list();
		$len = 8;
		foreach($list as $info){
			if($len < strlen($info[0])) $len = strlen($info[0]);
		}
		foreach($list as $info){
			\cmdman\Std::println('  '.str_pad($info[0],$len).' : '.$info[1]);
		}
		\cmdman\Std::println();
		exit;
	}
	if(\cmdman\Args::opt('h') === true || \cmdman\Args::opt('help') === true){
		\cmdman\Command::doc(\cmdman\Args::cmd());
		exit;
	}
	\cmdman\Command::exec(\cmdman\Args::cmd(),\cmdman\Args::opt('error-callback'));
}

