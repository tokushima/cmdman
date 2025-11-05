<?php
/**
 * リピート確認用
 * @param string $abc
 */
$i = rand(1,100);

\cmdman\Util::file_append(getcwd().'/work/RPT',$abc.': '.$i.' '.date('YmdHis').PHP_EOL);

if($i % 5 === 0){
    \cmdman\Util::file_append(getcwd().'/work/RPT','Error '.date('YmdHis').PHP_EOL);
    \cmdman\Std::println_danger('Error');
    \cmdman\Util::exit_error();
}
\cmdman\Std::println_primary('Wait');
\cmdman\Util::file_append(getcwd().'/work/RPT','Wait '.date('YmdHis').PHP_EOL);
\cmdman\Util::exit_wait();

