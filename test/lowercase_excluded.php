<?php
// 小文字で始まるディレクトリは無視されるテスト
$result = \test\Helper::cmd('aaa.bbb.ccc');
eq(true, strpos($result, 'command not found') !== false);
