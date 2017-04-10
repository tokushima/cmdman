<?php
/**
 * Repeat command execution
 * @param string $cmd Command to repeat　@['require'=>true]
 * @param string $pid PID file path
 * @param integer $wt Waiting time
 * @param integer $ws Waiting status number
 * @param string $out Path to output the last result
 * @param boolean $force Forced execution
 */
$php = $_SERVER['_'];
$self = $_SERVER['PHP_SELF'];
$command = $php.' '.$self.' '.$cmd;
$wait_status = empty($ws) ? 19 : $ws;
$wait_time = empty($wt) ? 60 : $wt;
$ext_pcntl = extension_loaded('pcntl');
$ext_posix = extension_loaded('posix');
$pid = (empty($pid) || substr($pid,-4) == '.pid') ? $pid : $pid.'.pid';

if(!empty($out)){
	if($out != 'stdout'){
		\cmdman\Util::file_write($out,'');
		$out = realpath($out);
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
		throw new \RuntimeException('Already running');
	}else{
		\cmdman\Util::file_write(
			$pid,
			sprintf('Start,%s,%s'.PHP_EOL,
				date('Y-m-d H:i:s'),
				(($ext_posix) ? posix_getpid() : '')
			)
		);
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

	if(!empty($out)){
		if($out == 'stdout'){
			print($rtn);
		}else{
			\cmdman\Util::file_write($out,$rtn);
		}
	}	
	if(!empty($pid)){
		try{
			clearstatcache();
			list($st) = explode(',',trim(\cmdman\Util::file_read($pid)));
		}catch(\InvalidArgumentException $e){
			$st = 'Stop';
		}
		if($st === 'Stop'){
			$shutdown_func();
		}else{
			\cmdman\Util::file_write(
				$pid,
				sprintf('Called,%s,%s'.PHP_EOL,
					date('Y-m-d H:i:s'),
					(($ext_posix) ? posix_getpid() : '')
				)
			);
		}
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
