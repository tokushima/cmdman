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
		private static $opt = array();
		private static $value = array();
		private static $cmd;
		
		public static function init(){
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
		public static function opt($name,$default=false){
			return array_key_exists($name,self::$opt) ? self::$opt[$name][0] : $default;
		}
		public static function value($default=null){
			return isset(self::$value[0]) ? self::$value[0] : $default;
		}
		public static function opts($name){
			return array_key_exists($name,self::$opt) ? self::$opt[$name] : array();
		}
		public static function values(){
			return self::$value;
		}
		public static function cmd(){
			if(defined('CMDMAN_CMD_REPLACE_JSON')){
				$json = constant('CMDMAN_CMD_REPLACE_JSON');
				foreach(json_decode($json,true) as $alias => $real){
					if(strpos(self::$cmd,$alias) === 0){
						self::$cmd = str_replace($alias,$real,self::$cmd);
					}
				}
			}
			return self::$cmd;
		}
	}
	class Notfound extends \Exception{
	}
	/**
	 * command
	 */
	class Command{
		public static function init(){
			if(is_file($f=getcwd().'/bootstrap.php') || is_file($f=getcwd().'/vendor/autoload.php')){
				try{
					ob_start();
					include_once(realpath($f));
					ob_end_clean();
				}catch(\Exception $e){
				}
			}
		}
		private static function get_include_path(){
			$include_path = array();

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
					// vendor以外の定義されているパスを探す
					foreach($loader->getPrefixes() as $ns){
						foreach($ns as $path){
							$include_path[$path] = true;
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

				foreach((isset($file) ? array($file) : self::get_include_path()) as $p){
					if(is_file($f=($protocol.$p.'/'.str_replace('.','/',$command).'/cmd/'.$func.'.php'))){
						if(self::validdir(dirname(dirname($f)))){
							return $f;
						}
					}
				}
			}else{
				foreach((isset($file) ? array($file) : self::get_include_path()) as $p){
					if(is_file($f=($protocol.$p.'/'.str_replace('.','/',$command).'/cmd.php'))){
						if(self::validdir(dirname($f))){
							return $f;
						}
					}
				}
			}
			throw new \cmdman\Notfound($command.' not found.');
		}
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
					call_user_func_array($error_funcs,array($exception));
				}
			}
		}
		private static function get_docuemnt($file){
			return (preg_match('/\/\*\*.+?\*\//s',file_get_contents($file),$m)) ?
				trim(preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array('/'.'**','*'.'/'),'',$m[0]))) :
				'';
		}
		private static function get_summary($file){
			$doc = trim(preg_replace('/@.+/','',self::get_docuemnt($file)));
			list($summary) = explode(PHP_EOL,$doc);
			return $summary;
		}
		private static function get_params($command){
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
		public static function find_cmd(&$list,$r,$realpath=null){
			if($r instanceof \RecursiveDirectoryIterator){
				$it = $r;
				$r = $r->getFilename();				
			}else{
				$it = new \RecursiveDirectoryIterator($r,\FilesystemIterator::CURRENT_AS_FILEINFO|\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::UNIX_PATHS);
			}
			$it = new \RecursiveIteratorIterator($it
				,\RecursiveIteratorIterator::SELF_FIRST
			);
			foreach($it as $f){
				if(self::validdir($f->getPathname())){
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
								if(strpos($f->getPathname(),'phar://') !== false){
									$class = $realpath.'#'.str_replace('/','.',substr($f->getPathname(),strpos($f->getPathname(),'.phar/')+6));
								}else{
									$class = str_replace('/','.',substr($f->getPathname(),strlen($r)+1));
								}
								$list[$fi->getPathname()] = array($class.'::'.substr($fi->getFilename(),0,-4),
																self::get_summary($fi->getPathname())
															);
							}
						}
					}
				}
			}				
		}
		public static function get_list(){
			$list = array();			
			foreach(self::get_include_path() as $p){
				if(($r = realpath($p)) !== false){
					self::find_cmd($list,$r);
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
		public static function read($msg,$default=null,$choice=array(),$multiline=false,$silently=false){
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
		public static function silently($msg,$default=null,$choice=array(),$multiline=false){
			return self::read($msg,$default,$choice,$multiline,true);
		}
		/**
		 * 色付きでプリント
		 * @param string $msg
		 * @param string $color ANSI Colors
		 */
		public static function println($msg='',$color='0'){
			if(substr(PHP_OS,0,3) != 'WIN'){
				print("\033[".$color."m");
				print($msg.PHP_EOL);
				print("\033[0m");
			}else{
				print($msg.PHP_EOL);
			}
		}
		/**
		 * White
		 * @param string $msg
		 */
		public static function println_default($msg){
			self::println($msg,'37');
		}
		/**
		 * Blue
		 * @param string $msg
		 */
		public static function println_primary($msg){
			self::println($msg,'34');
		}
		/**
		 * Green
		 * @param string $msg
		 */
		public static function println_success($msg){
			self::println($msg,'32');
		}
		/**
		 * Cyan
		 * @param string $msg
		 */
		public static function println_info($msg){
			self::println($msg,'36');
		}
		/**
		 * Yellow
		 * @param string $msg
		 */
		public static function println_warning($msg){
			self::println($msg,'33');
		}
		/**
		 * Red
		 * @param string $msg
		 */
		public static function println_danger($msg){
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
	\cmdman\Args::init();
	\cmdman\Command::init();
	
	$usage = function(){
		\cmdman\Std::println('cmdman 0.3.3 (PHP '.phpversion().')');
		$php = isset($_ENV['_']) ? $_ENV['_'] : 'php';
		\cmdman\Std::println_info(sprintf('Type \'%s %s subcommand --help\' for usage.'.PHP_EOL,basename($php),basename(__FILE__)));
		\cmdman\Std::println_primary('Subcommands:');		
	};
	$show = function($list){
		$len = 8;
		foreach($list as $info){
			if($len < strlen($info[0])) $len = strlen($info[0]);
		}
		foreach($list as $info){
			\cmdman\Std::println('  '.str_pad($info[0],$len).' : '.$info[1]);
		}
		\cmdman\Std::println();		
	};
	$get_list = function(){
		$list = \cmdman\Command::get_list();
		$list[] = array('extract','Extract the contents of a phar archive');
		$list[] = array('getebi','Download ebi');		
		$list[] = array('gettestman','Download Testman');
		$list[] = array('testman','Testman execute');
		$list[] = array('getcomposer','Download Composer');
		$list[] = array('composer','Composer update (--prefer-dist)');
		return $list;
	};
	if(\cmdman\Args::cmd() == null){
		$usage();
		$list = $get_list();
		$show($list);
		exit;
	}else if(is_file(\cmdman\Args::cmd())){ // find phar file
		$list = array();
		$usage();
		\cmdman\Command::find_cmd($list,new \Phar(realpath(\cmdman\Args::cmd())),\cmdman\Args::cmd());
		$show($list);
		exit;
	}
	if(\cmdman\Args::opt('h') === true || \cmdman\Args::opt('help') === true){
		try{
			\cmdman\Command::doc(\cmdman\Args::cmd());
		}catch(\cmdman\Notfound $e){
			foreach($get_list() as $cmd){
				if(\cmdman\Args::cmd() == $cmd[0]){
					\cmdman\Std::println('Usage: '.$cmd[1]);
					exit;
				}
			}
			throw $e;
		}
		exit;
	}
	
	try{
		\cmdman\Command::exec(\cmdman\Args::cmd(),\cmdman\Args::opt('error-callback'));
	}catch(\cmdman\Notfound $e){
		switch(\cmdman\Args::cmd()){
			case 'getebi':
				\cmdman\Std::println_info('Downloading.. ebi.phar');
				$ebi = getcwd().'/ebi.phar';
				file_put_contents($ebi,file_get_contents('http://git.io/ebi.phar'));
				
				if(is_file($f=$ebi)){
					\cmdman\Std::println_success('Written '.realpath($f));
					\cmdman\Std::println_warning('Use it: php cmdman.phar ebi.phar');
				}else{
					\cmdman\Std::println_danger('Download fail..');
				}
				exit;
			case 'getcomposer':
				\cmdman\Std::println_info('Downloading.. composer.phar');
				$src = file_get_contents('https://getcomposer.org/installer');
				eval('?>'.$src);
				exit;
			case 'composer':
				$composer = null;
				if(class_exists('Composer\Autoload\ClassLoader')){
					$r = new \ReflectionClass('Composer\Autoload\ClassLoader');
					if(is_file($f=dirname(dirname(dirname($r->getFileName())).'/composer.phar'))){
						$composer = $f;
					}
				}
				if(empty($composer)){
					if(is_file($f=getcwd().'/composer.phar')){
						$composer = $f;
					}else if(is_file($f=getcwd().'/libs/composer.phar')){
						$composer = $f;
					}
				}
				if(!empty($composer)){
					include_once('phar://'.$composer.'/src/bootstrap.php');
						
					if(class_exists('Composer\Console\Application')){
						chdir(dirname($composer));
			
						$app = new \Composer\Console\Application();
						$app->run(new \Symfony\Component\Console\Input\ArgvInput(array('','update','--prefer-dist')));
						exit;
					}
				}
				\cmdman\Std::println_danger('composer.phar not found');
				exit;
			case 'gettestman':
				\cmdman\Std::println_info('Downloading.. testman.phar');
				if(!is_dir($dir=getcwd().'/test')){
					mkdir($dir,0777,true);
				}
				$testman = getcwd().'/test/testman.phar';
				file_put_contents($testman,file_get_contents('http://git.io/testman.phar'));
				
				if(is_file($f=$testman)){
					\cmdman\Std::println_success('Written '.realpath($f));
				}else{
					\cmdman\Std::println_danger('Download fail..');
				}
				exit;
			case 'extract':
				$pahr = \cmdman\Args::value();
				
				if(is_file($pahr)){
					(new Phar($pahr))->extractTo(\cmdman\Args::opt('o',getcwd().'/'.basename($pahr,'.phar')));
				}else{
					\cmdman\Std::println_danger($pahr.' not found');
				}
				exit;
		}
		\cmdman\Std::println_danger(\cmdman\Args::cmd().': command not found');
	}
}

