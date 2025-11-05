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
	$show($list);
	exit;
}else{
	if(strpos(\cmdman\Args::cmd(), '::') === false && !is_file(\cmdman\Args::cmd())){
		$hit_list = [];
		foreach(\cmdman\Command::get_list() as [$cmd]){
			if(preg_match('/\:\:'.\cmdman\Args::cmd() .'$/', $cmd)){
				$hit_list[] = $cmd;
			}
		}
		if(sizeof($hit_list) === 1){
			$_SERVER['argv'][1] = $hit_list[0];
			\cmdman\Args::init();
		}
	}

	if(is_file(\cmdman\Args::cmd())){
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
	}
}

if(\cmdman\Args::opt('h') === true || \cmdman\Args::opt('help') === true) {
	\cmdman\Command::doc(\cmdman\Args::cmd());
}else{
	try{
		\cmdman\Command::exec(\cmdman\Args::cmd(), \cmdman\Args::opt('error-callback'));
	}catch(\cmdman\NotFound $e){
		$usage();
		\cmdman\Std::println_danger(\cmdman\Args::cmd().': subcommand not found');
		\cmdman\Util::exit_error();
	}
}

