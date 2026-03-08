<?php
// boolean型パラメータのテスト
eq('YES:Q',\test\Helper::cmd('aaa.bbb.OptBool --flag true'));
eq('NO:V',\test\Helper::cmd('aaa.bbb.OptBool --flag false --verbose true'));
eq('YES:V',\test\Helper::cmd('aaa.bbb.OptBool --flag true --verbose true'));
eq('NO:Q',\test\Helper::cmd('aaa.bbb.OptBool --flag false'));
