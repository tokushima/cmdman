<?php
// initアノテーションのテスト (デフォルト値)
eq('default_name:10',\test\Helper::cmd('aaa.bbb.OptInit'));
eq('hello:5',\test\Helper::cmd('aaa.bbb.OptInit --name hello --count 5'));
eq('hello:10',\test\Helper::cmd('aaa.bbb.OptInit --name hello'));
eq('default_name:3',\test\Helper::cmd('aaa.bbb.OptInit --count 3'));
