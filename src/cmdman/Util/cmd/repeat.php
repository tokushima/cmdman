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

$pid = \cmdman\Args::opt('pid');
$wait_status = empty($ws) ? 19 : $ws;
$wait_time = empty($wt) ? 60 : $wt;

$pstatus_func = function($pid,$st=null,$rt=null){
	if(!empty($st)){
		\cmdman\Util::file_write($pid,$st.','.date('Y-m-d H:i:s').','.$rt.PHP_EOL);
		return $st;
	}
	try{
		$status = explode(',',trim(\cmdman\Util::file_read($pid)));
	}catch(\InvalidArgumentException $e){
		return 'NONE';
	}
	return $status[0];
};
if(!empty($out)){
	if($out != 'stdout'){
		\cmdman\Util::file_write($out,'');
		$out = realpath($out);
	}
}
if(!empty($pid)){
	$pid = (substr($pid,-4) == '.pid') ? $pid : $pid.'.pid';

	if(file_exists($pid)){
		throw new \RuntimeException('Already running');
	}else{
		$pstatus_func($pid,'Called');
		$pid = realpath($pid);
	}
}

while(true){
	ob_start();
		system($command,$return_var);
	$rtn = ob_get_clean();

	if(!empty($out)){
		if($out == 'stdout'){
			print($rtn.PHP_EOL);
		}else{
			\cmdman\Util::file_write($out,$rtn.PHP_EOL);
		}
	}
	if(!empty($pid)){
		clearstatcache();
			
		if($pstatus_func($pid) == 'Stop'){
			unlink($pid);
			break;
		}
		$pstatus_func($pid,'Called',$return_var);
	}
	if($return_var !== 0){
		if($return_var === $wait_status){
			sleep($wait_time);
		}else{
			if($force){
				sleep($wait_time);
			}else{
				break;
			}
		}
	}
}
