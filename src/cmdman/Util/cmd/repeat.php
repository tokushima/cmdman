<?php
/**
 * Run a command repeatedly at a fixed interval
 * @param int $interval Wait interval between executions (sec) @['init'=>60]
 * @param string $pid PID file path for daemonized execution
 */
$target = \cmdman\Args::value();
if(empty($target)){
	$legacy_cmd = \cmdman\Args::opt('cmd');
	if(is_string($legacy_cmd)){
		$target = $legacy_cmd;
	}
}
if(empty($target)){
	\cmdman\Std::println_danger('target command required');
	\cmdman\Util::exit_error();
}
if(empty($interval)){
	$legacy_wt = \cmdman\Args::opt('wt');
	$interval = is_numeric($legacy_wt) ? (int)$legacy_wt : 60;
}
if(empty($pid)){
	$legacy_daemon = \cmdman\Args::opt('daemon');
	if(is_string($legacy_daemon)){
		$pid = $legacy_daemon;
	}
}
$self = $_SERVER['PHP_SELF'];
$argv = $_SERVER['argv'] ?? [];
$sep = array_search('--',$argv,true);
$extra = ($sep !== false) ? array_slice($argv,$sep + 1) : [];
$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($self).' '.escapeshellarg($target);
foreach($extra as $a){
	$command .= ' '.escapeshellarg($a);
}
$pid_file = (empty($pid) || str_ends_with($pid,'.pid')) ? $pid : $pid.'.pid';
$ext_pcntl = extension_loaded('pcntl');
$wait_status = \cmdman\Util::EXIT_WAIT;
$shutdown = false;

$shutdown_func = function() use($pid_file, &$shutdown){
	if($shutdown){
		return;
	}
	$shutdown = true;

	if(!empty($pid_file) && file_exists($pid_file)){
		unlink($pid_file);
	}
	exit;
};

if(!empty($pid_file)){
	if(file_exists($pid_file)){
		\cmdman\Std::println_warning('Already running');
		exit;
	}
	\cmdman\Util::file_write($pid_file,(extension_loaded('posix') ? posix_getpid() : '').','.$target);

	if($ext_pcntl){
		pcntl_signal(SIGINT,$shutdown_func);
		pcntl_signal(SIGTERM,$shutdown_func);
	}
	register_shutdown_function($shutdown_func);
}

while(true){
	system($command,$return_var);

	if($ext_pcntl){
		pcntl_signal_dispatch();
	}
	if(!empty($pid_file) && !file_exists($pid_file)){
		$shutdown_func();
	}
	if($return_var !== 0){
		if($return_var !== $wait_status){
			exit;
		}
		for($i=0;$i<$interval;$i++){
			sleep(1);

			if($ext_pcntl){
				pcntl_signal_dispatch();
			}
			if(!empty($pid_file) && $i % 60 === 59 && !file_exists($pid_file)){
				$shutdown_func();
			}
		}
	}
}
