<?php
/**
 * Extract the contents of a phar archive to a directory
 * 
 * @param string $file Phar file path @['require'=>true]
 * @param string $out Output path
 */
$args = \cmdman\Args::values();
	
\cmdman\Archive::unphar($file,$out);

