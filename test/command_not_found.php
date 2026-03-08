<?php
// 存在しないコマンドのエラーテスト
$result = \test\Helper::cmd('nonexistent.Command');
eq(true, strpos($result, 'command not found') !== false);
