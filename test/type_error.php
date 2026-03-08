<?php
// 型エラーのテスト (integer型に文字列を渡す)
$result = \test\Helper::cmd('aaa.bbb.Opt --abc AAA --def notanumber');
eq(true, strpos($result, 'must be an') !== false);
