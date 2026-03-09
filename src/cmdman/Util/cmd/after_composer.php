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

$parse = function(array $vars, string $cwd) use(&$exclude_json_pattern,&$copy_json_pattern,&$dummy_json_pattern): void{
	if(!array_key_exists('after',$vars)){
		return;
	}
	$after = $vars['after'];

	if(array_key_exists('exclude',$after)){
		foreach($after['exclude'] as $path){
			if(!is_string($path)){
				throw new \cmdman\InvalidJsonException('Invalid JSON: exclude');
			}
			$exclude_json_pattern[$path] = $path;
		}
	}
	if(array_key_exists('copy',$after)){
		foreach($after['copy'] as $filename => $dest){
			if(!is_string($filename) || !is_string($dest)){
				throw new \cmdman\InvalidJsonException('Invalid JSON: copy');
			}
			$copy_json_pattern[\cmdman\Util::path_absolute($cwd,$filename)] = $dest;
		}
	}

	$dummy_type_map = ['dummy'=>1,'dummy-class'=>1,'dummy-interface'=>2,'dummy-trait'=>3,'dummy-exception'=>4];
	foreach($dummy_type_map as $key => $type){
		if(array_key_exists($key,$after)){
			foreach($after[$key] as $classname){
				if(!is_string($classname)){
					throw new \cmdman\InvalidJsonException('Invalid JSON: dummy');
				}
				$dummy_json_pattern[$classname] = $type;
			}
		}
	}
};

if($json && is_file($f = getcwd().'/composer.json')){
	$parse(json_decode(file_get_contents($f),true),getcwd());
}

$exclude_target = [];
foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir,\FilesystemIterator::CURRENT_AS_FILEINFO|\FilesystemIterator::UNIX_PATHS)) as $f){
	if($f->isDir()){
		$path = preg_replace('/\/\.+$/','',$f->getPathname());
		$p = str_replace($dir,'',$path);

		foreach($exclude_pattern as $e){
			if(preg_match('/\/'.preg_quote($e,'/').'$/i',$p)){
				$exclude_target[$p] = $path;
			}
		}
	}else if($json && $f->getFilename() === 'composer.json'){
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

		if(!is_file($source) && !is_dir($source)){
			throw new \cmdman\AccessDeniedException($source);
		}

		$dest = \cmdman\Util::path_absolute($dir,$dest);

		if(is_dir($dest)){
			$dest = \cmdman\Util::path_absolute($dest,basename($source));
		}
		if(file_exists($dest)){
			\cmdman\Std::println_warning('    '.$dest.' (overwrite)');
		}else{
			\cmdman\Std::println('    '.$dest);
		}
		\cmdman\Util::copy($source,$dest);
	}
	\cmdman\Std::println();
}

if(!empty($dummy_json_pattern)){
	\cmdman\Std::println_info('Dummy'.PHP_EOL);

	foreach($dummy_json_pattern as $class => $type){
		$filename = \cmdman\Util::path_slash(str_replace('\\','/',$class),false);
		$namespace = str_replace('/','\\',dirname($filename));
		$classname = basename($filename);

		$filename = \cmdman\Util::path_absolute(\cmdman\Util::path_slash($dir,null,true).'/_dummy_sources',$filename).'.php';

		if(file_exists($filename)){
			\cmdman\Std::println_white('    '.$filename.' (exists)');
		}else{
			$keyword = match($type){
				2 => 'interface',
				3 => 'trait',
				default => 'class',
			};
			$extends = ($type === 4) ? ' extends \Exception' : '';

			$src = '<?php'.PHP_EOL;
			if($namespace !== '.'){
				$src .= 'namespace '.$namespace.';'.PHP_EOL;
			}
			$src .= $keyword.' '.$classname.$extends.'{'.PHP_EOL.'}'.PHP_EOL;

			\cmdman\Std::println('    '.$filename);
			\cmdman\Util::file_write($filename,$src);
		}
	}
	\cmdman\Std::println();
}
