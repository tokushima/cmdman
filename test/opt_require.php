<?php
// requireアノテーションのテスト (必須パラメータ)
eq('OK:test',\test\Helper::cmd('aaa.bbb.OptRequire --name test'));

// 必須パラメータ未指定時はエラーメッセージが出る
$result = \test\Helper::cmd('aaa.bbb.OptRequire');
eq(true, strpos($result, '--name required') !== false);
