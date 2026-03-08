<?php
/**
 * annotation parse test
 * @param string $name テスト名 @['require'=>true,'init'=>'hello']
 * @param integer $port ポート @['init'=>8080]
 * @param boolean $ssl SSL @['init'=>false]
 */
print($name . ':' . $port . ':' . ($ssl ? 'true' : 'false'));
