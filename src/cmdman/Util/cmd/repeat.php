<?php
/**
 * Run a command repeatedly at a fixed interval
 * @param string $cmd Command to execute @['require'=>true]
 * @param int $wt Wait interval between executions (sec) @['init'=>60]
 * @param string $daemon PID file path for daemonized execution
 * @param string $log Output file path, or "stdout" to print directly
 * @param string $php PHP binary path @['init'=>'php']
 * @param bool $force Continue even if command exits with error
 */
$php = empty($php) ? 'php' : $php;
ob_start();
	system(escapeshellarg($php).' -v');
$chk = ob_get_clean();

if(substr($chk,0,3) !== 'PHP'){
	\cmdman\Std::println_danger($php.' not a PHP');
	exit;
}
$self = $_SERVER['PHP_SELF'];
$command = escapeshellarg($php).' '.escapeshellarg($self).' '.$cmd;
$pid = (empty($daemon) || substr($daemon,-4) == '.pid') ? $daemon : $daemon.'.pid';
$ext_pcntl = extension_loaded('pcntl');
$wait_status = \cmdman\Util::EXIT_WAIT;
$wait_time = empty($wt) ? 60 : $wt;
$shutdown = false;

if(!empty($log) && $log !== 'stdout'){
	\cmdman\Util::file_append($log,'');
}

$shutdown_func = function() use($pid,&$shutdown){
	if($shutdown){
		return;
	}
	$shutdown = true;

	if(!empty($pid) && file_exists($pid)){
		unlink($pid);
	}
	exit;
};

if(!empty($pid)){
	if(file_exists($pid)){
		\cmdman\Std::println_warning('Already running');
		exit;
	}
	\cmdman\Util::file_write($pid,(extension_loaded('posix') ? posix_getpid() : '').','.$cmd);

	if($ext_pcntl){
		pcntl_signal(SIGINT,$shutdown_func);
		pcntl_signal(SIGTERM,$shutdown_func);
	}
	register_shutdown_function($shutdown_func);
}

while(true){
	ob_start();
		system($command,$return_var);
	$rtn = ob_get_clean();

	if(!empty($log)){
		if($log === 'stdout'){
			print($rtn);
		}else{
			\cmdman\Util::file_append($log,$rtn);
		}
	}
	if($ext_pcntl){
		pcntl_signal_dispatch();
	}
	if(!empty($pid) && !file_exists($pid)){
		$shutdown_func();
	}
	if($return_var !== 0){
		if($return_var !== $wait_status && !$force){
			exit;
		}
		for($i=0;$i<$wait_time;$i++){
			sleep(1);

			if($ext_pcntl){
				pcntl_signal_dispatch();
			}
			if(!empty($pid) && $i % 60 === 59 && !file_exists($pid)){
				$shutdown_func();
			}
		}
	}
}
