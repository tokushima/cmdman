<?php
namespace test;

class Helper{
	static public function cmd($sub){
		exec($_ENV['_'].' '.getcwd().'/cmdman.php '.$sub,$rtn);
		return implode(PHP_EOL,$rtn);
	}
	static public function result_path($path){
		return dirname(dirname(dirname(__DIR__))).$path;
	}
}