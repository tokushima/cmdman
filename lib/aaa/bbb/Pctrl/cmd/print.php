<?php

$m = microtime(true);

\cmdman\Util::pctrl(function($arr){
	sleep(2);
	
	foreach($arr as $i){
		print($i.' ');
	}	
},[[1,2,3],[4,5,6],[7,8,9],[10,11,12],[13,14],[15,16]]);

print('finished');
var_dump(microtime(true)-$m);
