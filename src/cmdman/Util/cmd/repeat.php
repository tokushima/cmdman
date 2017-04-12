<?php
/**
 * Repeat command execution
 * @param string $cmd Command to repeat　@['require'=>true]
 * @param string $daemon PID file path
 * @param string $log Path to output the last result
 * @param string $php PHP binary path 
 * @param boolean $force Forced execution
 * @param integer $wt Waiting time
 */
$php = empty($php) ? 'php' : $php;
ob_start();
	system($php.' -v');
$chk = ob_get_clean();

if(substr($chk,0,3) !== 'PHP'){
	\cmdman\Std::println_danger($php.' not a PHP');
	exit;
}
$self = $_SERVER['PHP_SELF'];
$command = $php.' '.$self.' '.$cmd;
$pid = (empty($daemon) || substr($daemon,-4) == '.pid') ? $daemon : $daemon.'.pid';
$ext_pcntl = extension_loaded('pcntl');
$wait_status = 19;
$wait_time = empty($wt) ? 60 : $wt;

if(!empty($log)){
	if($log != 'stdout'){
		\cmdman\Util::file_append($log,'');
		$log = realpath($log);
	}
}
$shutdown_func = function() use($pid){
	if(file_exists($pid)){
		unlink($pid);
	}
	exit;
};
if(!empty($pid)){
	if(file_exists($pid)){
		\cmdman\Std::println_warning('Already running');
		exit;
	}else{
		$value = (extension_loaded('posix') ? posix_getpid() : '').','.$cmd;
		\cmdman\Util::file_write($pid,$value);
		$pid = realpath($pid);
	}
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
		if($log == 'stdout'){
			print($rtn);
		}else{
			\cmdman\Util::file_append($log,$rtn);
		}
	}	
	if(!empty($pid) && !file_exists($pid)){
		$shutdown_func();
	}
	if($return_var !== 0){
		if($return_var !== $wait_status && !$force){
			exit;
		}
		for($i=0;$i<$wait_time;$i++){
			if($ext_pcntl){
				pcntl_signal_dispatch();
			}
			sleep(1);
		}
	}
	if($ext_pcntl){
		pcntl_signal_dispatch();
	}
}
