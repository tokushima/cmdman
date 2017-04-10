<?php
/**
 * Stop repeat command
 * @param string $pid PID file path @['require'=>true]
 */
clearstatcache();
$pid = (empty($pid) || substr($pid,-4) == '.pid') ? $pid : $pid.'.pid';
	
if(file_exists($pid)){
	\cmdman\Util::file_write(
		$pid,
		sprintf('Stop,%s',
			date('Y-m-d H:i:s')
		)
	);
}
for($i=1;$i<120;$i++){
	if(file_exists($pid)){
		if($i%5===0){
			\cmdman\Std::p('.');
		}
		sleep(1);
	}else{
		\cmdman\Std::println_success('Stopped');
		break;
	}
}
if($i >= 120){
	\cmdman\Std::println_danger('Failure');
}
