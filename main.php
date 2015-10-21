<?php
set_error_handler(function($n,$s,$f,$l){
	throw new \ErrorException($s,0,$n,$f,$l);
});
if(ini_get('date.timezone') == ''){
	date_default_timezone_set('Asia/Tokyo');
}
if(extension_loaded('mbstring')){
	if('neutral' == mb_language()){
		mb_language('Japanese');
	}
	mb_internal_encoding('UTF-8');
}
if(isset($_SERVER['SERVER_PORT'])){
	header('HTTP/1.1 403 Forbidden');
	print('Forbidden');
	exit;
}
\cmdman\Args::init();
\cmdman\Command::init();

$version = '0.4.0';
$usage = function() use($version){
	$php = isset($_ENV['_']) ? $_ENV['_'] : 'php';

	\cmdman\Std::println('cmdman '.$version.' (PHP '.phpversion().')');
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

if(\cmdman\Args::cmd() == null){
	$usage();

	$list = \cmdman\Command::get_list();
	
	if(!is_file('ebi.phar')){
		$list = array_merge($list,array(
			'ebi.phar'=>array('ebi.phar','Download ebi.phar'),
		));
	}
	$list = array_merge($list,array(
		'composer.phar'=>array('composer.phar','Download composer.phar'),
		'format'=>array('format','Source format'),
		'archice'=>array('archive','create phar package'),
	));
	$show($list);
	exit;
}else{
	if(is_file(\cmdman\Args::cmd())){ // find phar file
		$list = array();
		$usage();
		\cmdman\Command::find_cmd($list,new \Phar(realpath(\cmdman\Args::cmd())),\cmdman\Args::cmd());
		$show($list);
		exit;
	}else{
		switch(\cmdman\Args::cmd()){
			case 'composer.phar':
				\cmdman\Cmd::download_composer();
				break;
			case 'ebi.phar':
				\cmdman\Cmd::download_ebi();				
				break;
			case 'format':
				\cmdman\Cmd::source_format(getcwd());
				break;
			case 'archive':
				\cmdman\Cmd::phar(\cmdman\Args::value());
				break;
			default:
				\cmdman\Std::println_danger(\cmdman\Args::cmd().': command not found');
		}
		exit;
	}
}



if(\cmdman\Args::opt('h') === true || \cmdman\Args::opt('help') === true){
	try{
		\cmdman\Command::doc(\cmdman\Args::cmd());
	}catch(\cmdman\Notfound $e){
		foreach(\cmdman\Command::get_list() as $cmd){
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
	\cmdman\Std::println_danger(\cmdman\Args::cmd().': command not found');
}

