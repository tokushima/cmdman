<?php
/**
 * hoge 必須
 * @param string $hoge 必須 @['require'=>true]
 * @param integer $abc @['init'=>123]
 */
\cmdman\Std::println_success(date('Y-m-d H:i:s'));
var_dump($hoge);
var_dump($abc);

//sleep(rand(1,3));

if(rand(1,100) == 9){
	var_dump('error');
	\cmdman\Util::exit_error();
}
if(rand(1,10) == 2){
	var_dump('wait');
	\cmdman\Util::exit_wait();
}
exit();
