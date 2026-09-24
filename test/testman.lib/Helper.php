<?php
namespace test;

class Helper{
	static public function cmd($sub){
		// $_SERVER['_'] は testman 実行時に testman 自身を指すため、実行中の php を直接使う
		exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(getcwd().'/cmdman.phar').' '.$sub,$rtn);
		return implode(PHP_EOL,$rtn);
	}
	static public function result_path($path){
		return dirname(dirname(__DIR__)).$path;
	}
}