<?php
/**
 * Processing after composer update 
 * @param string $dir Target vendor folder @['require'=>true]
 * @param bool $json Check composer.json @['init'=>true]
 * @param bool $git Exclude .git
 */
$exclude_pattern = ['tests','test','examples','example'];
$exclude_json_pattern = [];
$copy_json_pattern = [];
$dummy_json_pattern = [];

if($git){
	$exclude_pattern[] = '.git';
}

$dir = realpath($dir);

if(!is_dir($dir)){
	throw new \cmdman\AccessDeniedException();
}

$parse = function($vars,$cwd) use(&$exclude_json_pattern,&$copy_json_pattern,&$dummy_json_pattern){
	if(array_key_exists('after',$vars)){
		if(array_key_exists('exclude',$vars['after'])){
			foreach($vars['after']['exclude'] as $path){
				if(!is_string($path)){
					throw new \cmdman\InvalidJsonException('Invalid JSON: exclude');
				}else{
					$exclude_json_pattern[$path] = $path;
				}
			}
		}
		if(array_key_exists('copy',$vars['after'])){
			foreach($vars['after']['copy'] as $filename => $dest){
				if(!is_string($filename) || !is_string($dest)){
					throw new \cmdman\InvalidJsonException('Invalid JSON: copy');
				}else{
					$filename = \cmdman\Util::path_absolute($cwd,$filename);
					$copy_json_pattern[$filename] = $dest;
				}
			}
		}
		if(array_key_exists('dummy',$vars['after'])){
			foreach($vars['after']['dummy'] as $classname){
				if(!is_string($classname)){
					throw new \cmdman\InvalidJsonException('Invalid JSON: dummy');
				}else{
					$dummy_json_pattern[$classname] = 1;
				}
			}
		}
		if(array_key_exists('dummy-class',$vars['after'])){
			foreach($vars['after']['dummy-class'] as $classname){
				if(!is_string($classname)){
					throw new \cmdman\InvalidJsonException('Invalid JSON: dummy');
				}else{
					$dummy_json_pattern[$classname] = 1;
				}
			}
		}
		if(array_key_exists('dummy-interface',$vars['after'])){
			foreach($vars['after']['dummy-interface'] as $classname){
				if(!is_string($classname)){
					throw new \cmdman\InvalidJsonException('Invalid JSON: dummy');
				}else{
					$dummy_json_pattern[$classname] = 2;
				}
			}
		}
		if(array_key_exists('dummy-trait',$vars['after'])){
			foreach($vars['after']['dummy-trait'] as $classname){
				if(!is_string($classname)){
					throw new \cmdman\InvalidJsonException('Invalid JSON: dummy');
				}else{
					$dummy_json_pattern[$classname] = 3;
				}
			}
		}
		if(array_key_exists('dummy-exception',$vars['after'])){
			foreach($vars['after']['dummy-exception'] as $classname){
				if(!is_string($classname)){
					throw new \cmdman\InvalidJsonException('Invalid JSON: dummy');
				}else{
					$dummy_json_pattern[$classname] = 4;
				}
			}
		}
	}
};

if($json && ($f = (getcwd().'/composer.json')) && is_file($f)){
	$parse(json_decode(file_get_contents($f),true),getcwd());
}

$exclude_target = [];
foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir,\FilesystemIterator::CURRENT_AS_FILEINFO|\FilesystemIterator::UNIX_PATHS)) as $f){
	if($f->isDir()){
		$path = preg_replace(['/\/\.+$/'],'',$f->getPathname());
		$p = str_replace($dir,'',$path);
		
		foreach($exclude_pattern as $e){
			if(preg_match('/\/'.preg_quote($e).'$/i',$p)){
				$exclude_target[$p] = $path;
			}
		}
	}else if($json && $f->getFilename() == 'composer.json'){
		$parse(json_decode(file_get_contents($f->getPathname()),true),dirname($f->getPathname()));
	}
}

if(!empty($exclude_target)){
	\cmdman\Std::println_info('Matched exclusion pattern'.PHP_EOL);
	
	foreach($exclude_target as $path){
		if(is_dir($path)){
			\cmdman\Std::println('  '.$path);
			\cmdman\Util::rm($path);
		}
	}
	\cmdman\Std::println();
}


if(!empty($exclude_json_pattern)){
	\cmdman\Std::println_info('Exclude'.PHP_EOL);
	
	foreach($exclude_json_pattern as $path){
		$path = \cmdman\Util::path_absolute($dir,\cmdman\Util::path_slash($path,false));
		
		if(is_dir($path)){
			\cmdman\Std::println_primary('    '.$path);
			\cmdman\Util::rm($path);
		}else if(is_file($path)){
			\cmdman\Std::println('    '.$path);
			\cmdman\Util::rm($path);
		}else{
			\cmdman\Std::println_white('    '.$path.' (not found)');
		}
	}
	\cmdman\Std::println();
}


if(!empty($copy_json_pattern)){
	\cmdman\Std::println_info('Copy'.PHP_EOL);
	
	foreach($copy_json_pattern as $source => $dest){
		$source = \cmdman\Util::path_absolute(getcwd(),$source);
		
		if(is_file($source) || is_dir($source)){
			$dest = \cmdman\Util::path_absolute($dir,$dest);
			
			if(is_dir($dest)){
				$dest = cmdman\Util::path_absolute($dest,basename($source));
			}
			if(file_exists($dest)){
				\cmdman\Std::println_warning('    '.$dest.' (overwrite)');
			}else{
				\cmdman\Std::println('    '.$dest);
			}
			\cmdman\Util::copy($source, $dest);
		}else{
			throw new \cmdman\AccessDeniedException($source);
		}

	}
	\cmdman\Std::println();
}

if(!empty($dummy_json_pattern)){
	\cmdman\Std::println_info('Dummy'.PHP_EOL);
	
	foreach($dummy_json_pattern as $class => $type){
		$filename = \cmdman\Util::path_slash(str_replace('\\','/',$class),false);
		$namespae = str_replace('/','\\',dirname($filename));
		$classname = basename($filename);
		
		$filename = \cmdman\Util::path_absolute(\cmdman\Util::path_slash($dir,null,true).'/_dummy_sources',$filename).'.php';
		
		if(file_exists($filename)){
			\cmdman\Std::println_white('    '.$filename.' (exists)');
		}else{
			$src = '<?php'.PHP_EOL;
			
			if($namespae != '.'){
				$src .= 'namespace '.$namespae.';'.PHP_EOL;
			}
			if($type == 1 || $type == 4){
				$src .= 'class';
			}else if($type == 2){
				$src .= 'interface';
			}else if($type == 3){
				$src .= 'trait';
			}
			$src .= ' '.$classname;
			
			if($type == 4){
				$src .= ' extends \Exception';
			}
			$src .= '{'.PHP_EOL;
			$src .= '}'.PHP_EOL;
			
			\cmdman\Std::println('    '.$filename);
			\cmdman\Util::file_write($filename,$src);
		}
	}
	\cmdman\Std::println();
}



