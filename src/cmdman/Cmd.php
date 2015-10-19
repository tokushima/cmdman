<?php
namespace cmdman;

class Cmd{
	public static function download_ebi(){
		file_put_contents('ebi.phar',file_get_contents('http://git.io/ebi.phar'));
		
		if(is_file('ebi.phar')){
			\cmdman\Std::println_success('ebi successfully installed to: '.realpath('ebi.phar'));
		}		
	}
	public static function download_composer(){
		$composer_json = <<< _JSON_
{
	"config":{
		"preferred-install": "dist"
	},
	"require": {
		"tokushima/ebi": "dev-master"
	}
}
_JSON_;
		
		if(!is_file('composer.json')){
			file_put_contents('composer.json',$composer_json);
			\cmdman\Std::println_success('Written: '.realpath('composer.json'));
		}
		eval('?>'.file_get_contents('https://getcomposer.org/installer'));
		
	}
}
