<?php
// float型パラメータのテスト (require + init)
eq('3.14:1.5',\test\Helper::cmd('aaa.bbb.OptFloat --num 3.14'));
eq('0.5:2',\test\Helper::cmd('aaa.bbb.OptFloat --num 0.5 --rate 2.0'));
