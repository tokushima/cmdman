<?php
/**
 * Exclude vendor files
 * @param string $dir Target vendor folder @['require'=>true]
 * @param boolean $json Check composer.json @['init'=>true]
 * @param boolean $git Exclude .git
 */
$exclude_pattern = ['tests','test','examples','example'];
$json_pattern = [];

if($git){
	$exclude_pattern[] = '.git';
}

$dir = realpath($dir);

if(!is_dir($dir)){
	throw new \cmdman\AccessDeniedException();
}
	
$target = [];
foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir,\FilesystemIterator::CURRENT_AS_FILEINFO|\FilesystemIterator::UNIX_PATHS)) as $f){
	if($f->isDir()){
		$path = preg_replace(['/\/\.+$/'],'',$f->getPathname());
		$p = str_replace($dir,'',$path);
		
		foreach($exclude_pattern as $e){
			if(preg_match('/\/'.preg_quote($e).'$/i',$p)){
				$target[$p] = $path;
			}
		}
	}else if($json && $f->getFilename() == 'composer.json'){
		$vars = json_decode(file_get_contents($f->getPathname()),true);
		
		if(array_key_exists('ex-vendor-exclude',$vars)){
			$json_pattern[$f->getPathname()] = $vars['ex-vendor-exclude'];
		}
	}
}

if(!empty($target)){
	\cmdman\Std::println_info('Matched exclusion pattern'.PHP_EOL);
	
	foreach($target as $path){
		if(is_dir($path)){
			\cmdman\Std::println_primary('  '.$path);
			\cmdman\Util::rm($path);
		}
	}
	\cmdman\Std::println();
}

if(!empty($json_pattern)){
	\cmdman\Std::println_info('ex-vendor-exclud'.PHP_EOL);
	
	foreach($json_pattern as $cond => $paths){
		\cmdman\Std::println_warning('  '.$cond);
		
		foreach($paths as $p){
			$p = \cmdman\Util::path_absolute($dir,\cmdman\Util::path_slash($p,false));
			
			if(is_dir($p)){
				\cmdman\Std::println_primary('    '.$p);
				\cmdman\Util::rm($p);
			}else if(is_file($p)){
				\cmdman\Std::println('    '.$p);
				\cmdman\Util::rm($p);
			}
		}
	}
	\cmdman\Std::println();
}



