<?php
set_error_handler(function($n, $s, $f, $l) {
	throw new \ErrorException($s, 0, $n, $f, $l);
});
if(ini_get('date.timezone') == '') {
	date_default_timezone_set('Asia/Tokyo');
}
if(extension_loaded('mbstring')) {
	if('neutral' == mb_language()) {
		mb_language('Japanese');
	}
	mb_internal_encoding('UTF-8');
}
if(isset($_SERVER['SERVER_PORT'])) {
	header('HTTP/1.1 403 Forbidden');
	print('Forbidden') ;
	exit();
}
\cmdman\Args::init();
\cmdman\Command::init();

$version = is_file(__DIR__.'/version') ? file_get_contents(__DIR__.'/version') : '';

$usage = function() use($version) {
	$php = isset($_ENV['_']) ? $_ENV['_'] : 'php';
	
	\cmdman\Std::println('cmdman '.$version.' (PHP '.phpversion().')');
	\cmdman\Std::println_info(sprintf('Type \'%s cmdman.phar subcommand --help\' for usage.'.PHP_EOL, basename($php)));
	\cmdman\Std::println_primary('Subcommands:');
};
$show = function($list) {
	$len = 8;
	
	foreach($list as $info){
		if($len < strlen($info[0]))
			$len = strlen($info[0]);
	}
	foreach($list as $info){
		\cmdman\Std::println('  '.str_pad($info[0],$len).' : '.$info[1]);
	}
	\cmdman\Std::println();
};

if(\cmdman\Args::cmd() == null) {
	$usage();
	
	$list = \cmdman\Command::get_list();
	
	if(!is_file('ebi.phar') && !class_exists('\ebi\Conf')){
		$list = array_merge($list,[
			'ebi.phar'=>['ebi.phar','Download ebi.phar']
		]);
	}
	if(!is_file('composer.phar')){
		$list = array_merge($list,[
			'composer.phar'=>['composer.phar','Download composer.phar'],
		]);		
	}
	$list = array_merge($list,[	
		'archive'=>['archive','Creating Phar Archives'],
		'extract'=>['extract','Extract the contents of a phar archive to a directory']
	]);
	$show($list);
	exit();
}else{
	if(is_file(\cmdman\Args::cmd())) { // find phar file
		$list = [];
		
		if(\cmdman\Args::opt('v') === true || \cmdman\Args::opt('version') === true){
			if(is_file($f='phar://'.realpath(\cmdman\Args::cmd()).'/version')){
				\cmdman\Std::println_info('Version '.file_get_contents($f));
				exit;
			}
		}
		$usage();
		\cmdman\Command::find_cmd($list, new \Phar(realpath(\cmdman\Args::cmd())), \cmdman\Args::cmd());
		$show($list);
		exit;
	}else{
		try{
			switch(\cmdman\Args::cmd()) {
				case 'composer.phar' :
					eval('?>'.file_get_contents('https://getcomposer.org/installer'));
					
					exit;
				case 'ebi.phar' :
					file_put_contents('ebi.phar',file_get_contents('http://git.io/ebi.phar'));
					
					if(is_file('ebi.phar')){
						\cmdman\Std::println_success('ebi successfully installed to: '.realpath('ebi.phar'));
					}				
					exit;
				case 'archive' :
					$args = \cmdman\Args::values();
					
					\cmdman\Cmd::phar((isset($args[0]) ? $args[0] : null),(isset($args[1]) ? $args[1] : null));
					exit();
				case 'extract' :
					$args = \cmdman\Args::values();
					
					\cmdman\Cmd::unphar((isset($args[0]) ? $args[0] : null),(isset($args[1]) ? $args[1] : null));
					exit();
				default:
			}
		}catch(\Exception $e){
			\cmdman\Std::println_danger(get_class($e).': '.$e->getMessage());
			exit;
		}
	}
}

if(\cmdman\Args::opt('h') === true || \cmdman\Args::opt('help') === true) {
	try {
		\cmdman\Command::doc(\cmdman\Args::cmd());
	}catch(\cmdman\Notfound $e){
		foreach(\cmdman\Command::get_list() as $cmd){
			if(\cmdman\Args::cmd() == $cmd[0]) {
				\cmdman\Std::println('Usage: '.$cmd[1]);
				exit();
			}
		}
		throw $e;
	}
	exit();
}

try {
	\cmdman\Command::exec(\cmdman\Args::cmd(), \cmdman\Args::opt('error-callback'));
}catch(\cmdman\Notfound $e){
	$usage();
	\cmdman\Std::println_danger(\cmdman\Args::cmd().': subcommand not found');
}

