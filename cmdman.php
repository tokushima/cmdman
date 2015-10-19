<?php
spl_autoload_register(function($c){
	$c = str_replace('\\','/',$c);
	
	if(substr($c,0,7) == 'cmdman/' && is_file($f=__DIR__.'/src/'.$c.'.php')){
		require_once($f);
		return true;
	}
	return false;
},true,false);

include('main.php');


