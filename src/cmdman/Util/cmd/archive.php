<?php
/**
 * Creating Phar Archives
 * @param string $dir Library path @['require'=>true]
 * @param string $out Output path
 */
$args = \cmdman\Args::values();
	
\cmdman\Archive::phar($dir,$out);
