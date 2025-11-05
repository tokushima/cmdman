<?php
namespace test;

class Helper{
	static public function cmd($sub){
		$php = isset($_ENV['_']) ? $_ENV['_'] : (isset($_SERVER['_']) ? $_SERVER['_'] : 'php');
		exec($php.' '.getcwd().'/cmdman.phar '.$sub,$rtn);
		return implode(PHP_EOL,$rtn);
	}
	static public function result_path($path){
		return dirname(dirname(__DIR__)).$path;
	}
}