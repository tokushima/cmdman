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
	


#Args type

	@param
		string
		integer
		float
		boolean

#Args annotation

	init: mixed
	require: boolean

##sample

	<?php
	/**
	 * hoge 必須
	 * @param string $hoge 必須 @['require'=>true]
	 * @param integer $abc @['init'=>123]
	 */
	var_dump($hoge);
	var_dump($abc);
	


#Special script file

	__setup__.php
	__teardown__.php
	__exception__.php


#Define

	CMDMAN_ERROR_CALLBACK = func
	CMDMAN_CMD_REPLACE_JSON = json text


#Methods

	\cmdman\Std::
		/**
		 * メッセージの出力
		 */
		println_danger($msg)
		println_default($msg)
		println_info($msg)
		println_primary($msg)
		println_success($msg)
		println_warning($msg)
		
		/**
		 * 標準入力からの入力を取得する
		 * @param string $msg 入力待ちのメッセージ
		 * @param string $default 入力が空だった場合のデフォルト値
		 * @param string[] $choice 入力を選択式で求める
		 * @param boolean $multiline 複数行の入力をまつ、終了は行頭.(ドット)
		 * @param boolean $silently 入力を非表示にする(Windowsでは非表示になりません)
		 * @return string
		 */
		read($msg,$default=null,$choice=array(),$multiline=false,$silently=false)
		
		/**
		 * readのエイリアス、入力を非表示にする
		 * Windowsでは非表示になりません
		 * @param string $msg 入力待ちのメッセージ
		 * @param string $default 入力が空だった場合のデフォルト値
		 * @param string[] $choice 入力を選択式で求める
		 * @param boolean $multiline 複数行の入力をまつ、終了は行頭.(ドット)
		 * @return string
		 */
		silently($msg,$default=null,$choice=array(),$multiline=false)

	