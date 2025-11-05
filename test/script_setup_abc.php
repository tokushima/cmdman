<?php

$result = explode(PHP_EOL,\test\Helper::cmd('aaa.bbb.ScriptSetup::abc'));
eq(3,sizeof($result));

eq(\test\Helper::result_path('/lib/aaa/bbb/ScriptSetup/cmd/__setup__.php'),$result[0]);
eq(\test\Helper::result_path('/lib/aaa/bbb/ScriptSetup/cmd/abc.php'),$result[1]);
eq(\test\Helper::result_path('/lib/aaa/bbb/ScriptSetup/cmd/__teardown__.php'),$result[2]);




