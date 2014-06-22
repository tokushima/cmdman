cmdman
=========
Class tool launcher (PHP 5 >= 5.3.0)


#Download
	> curl -LO http://git.io/cmdman.phar

#Create cmd file
	\abc\def\Ghi.php
	 -> [lib dir]/abc/def/Ghi.php
	 
	 
	 => [lib dir]/abc/def/Ghi/cmd.php
	 or
	 => [lib dir]/abc/def/Ghi/cmd/xyz.php
	 => [lib dir]/abc/def/Ghi/cmd/ebi.php	 

#Run command
	> php cmdman.phar abc.def.Ghi [arg] --paramname value --paramname value 
	

