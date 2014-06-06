<?php
namespace test;

class Helper{
	static public function cmd($sub){
		exec($_ENV['_'].' '.getcwd().'/newcmdman.php '.$sub,$rtn);
		return implode(PHP_EOL,$rtn);
	}
	static public function result_path($path){
		return dirname(__DIR__).$path;
	}
}