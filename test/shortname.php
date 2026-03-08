<?php
// サブコマンド名の短縮呼び出しテスト (一意なサブコマンド名で名前空間省略)
$full = \test\Helper::cmd('aaa.bbb.Aaa::vars --a X');
$short = \test\Helper::cmd('vars --a X');
eq($full, $short);
