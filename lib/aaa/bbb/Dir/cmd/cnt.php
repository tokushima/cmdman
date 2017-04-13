<?php
/**
 * @param string $file @['require'=>true]
 */
if(file_exists($file)){
	$no = file_get_contents($file);
}else{
	$no = 0;
}
$no++;
file_put_contents($file,$no);

\cmdman\Std::println_success(date('Y-m-d H:i:s').' '.$no);
sleep(1);

if(rand(1,10) == 2){
	\cmdman\Std::println_info('Wait '.date('Y-m-d H:i:s'));
	\cmdman\Util::exit_wait();
}
