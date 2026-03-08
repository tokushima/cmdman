<?php
// --helpオプションのテスト
$result = \test\Helper::cmd('aaa.bbb.Opt --help');
eq(true, strpos($result, 'Usage:') !== false);
eq(true, strpos($result, 'Options:') !== false);
eq(true, strpos($result, '--abc') !== false);
eq(true, strpos($result, '--def') !== false);
