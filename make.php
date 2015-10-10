<?php
/**
 *  php -d phar.readonly=0 **.php
 */
$output = __DIR__.'/cmdman.phar';
$filename = 'cmdman';

if(is_file($output)){
	unlink($output);
}
try{
	$phar = new Phar($output,0,$filename.'.phar');
	$phar['cmdman.php'] = file_get_contents(__DIR__.'/cmdman.php');
	$stab = <<< 'STAB'
<?php
		Phar::mapPhar('%s.phar');
		include 'phar://%s.phar/%s.php';
		__HALT_COMPILER();
?>
STAB;
	$phar->setStub(sprintf($stab,$filename,$filename,$filename));
	$phar->compressFiles(Phar::GZ);
	
	if(is_file($output)){
		print('Created '.$output.' ['.filesize($output).' byte]'.PHP_EOL);
	}else{
		print('Failed '.$output.PHP_EOL);
	}
}catch(UnexpectedValueException $e){
	print($e->getMessage().PHP_EOL.'usage: php -d phar.readonly=0 '.basename(__FILE__).PHP_EOL);
}catch (Exception $e){
	var_dump($e->getMessage());
	var_dump($e->getTraceAsString());
}
