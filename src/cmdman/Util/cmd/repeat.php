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
$pcntl = extension_loaded('pcntl');
$posix = extension_loaded('posix');

if(!empty($out)){
	if($out != 'stdout'){
		\cmdman\Util::file_write($out,'');
		$out = realpath($out);
	}
}
$pstatus_func = function($pid,$st=null,$rt=null) use($posix){
	if(!empty($st)){
		\cmdman\Util::file_write($pid,$st.(($posix) ? ','.posix_getpid() : '').','.date('Y-m-d H:i:s').','.$rt.PHP_EOL);
		return $st;
	}
	try{
		$status = explode(',',trim(\cmdman\Util::file_read($pid)));
	}catch(\InvalidArgumentException $e){
		return 'NONE';
	}
	return $status[0];
};
if(!empty($pid)){
	$pid = (substr($pid,-4) == '.pid') ? $pid : $pid.'.pid';
	
	if(file_exists($pid)){
		throw new \RuntimeException('Already running');
	}else{
		$pstatus_func($pid,'Called');
		$pid = realpath($pid);
	}
	$shutdown_func = function() use($pid){
		if(file_exists($pid)){
			unlink($pid);
		}
		exit;
	};
	if($pcntl){
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
	if($return_var !== 0){
		if($return_var !== $wait_status && !$force){
			exit;
		}
		for($i=0;$i<$wait_time;$i++){
			if($pcntl){
				pcntl_signal_dispatch();
			}
			sleep(1);
		}
	}
	if($pcntl){
		pcntl_signal_dispatch();
	}
}
