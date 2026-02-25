# cmdman

PHP製の軽量CLIコマンドフレームワーク (PHP 7.1+)

名前空間ベースのルーティングでコマンドを管理・実行します。

## コマンドの実行

```sh
php cmdman.phar <コマンド名> [--オプション名 値]
```

コマンド名は `.` で名前空間を、`::` でサブコマンドを区切ります。

```sh
php cmdman.phar abc.def.Ghi
php cmdman.phar abc.def.Ghi::sub --name value
```

サブコマンド名がプロジェクト内で一意であれば、名前空間を省略できます。

```sh
php cmdman.phar sub    # abc.def.Ghi::sub が一意なら省略可
```

### コマンド一覧の表示

```sh
php cmdman.phar
```

### ヘルプの表示

```sh
php cmdman.phar abc.def.Ghi --help
```

## コマンドの定義

`lib/` ディレクトリ配下にPHPファイルを配置します。
ディレクトリ名の先頭は**大文字**にしてください。

### パターン1: 単一コマンド (`cmd.php`)

```
lib/abc/def/Ghi/cmd.php
```

```sh
php cmdman.phar abc.def.Ghi
```

```php
<?php
/**
 * コマンドの説明文
 */
echo 'Hello';
```

### パターン2: サブコマンド (`cmd/` ディレクトリ)

```
lib/abc/def/Ghi/cmd/foo.php
lib/abc/def/Ghi/cmd/bar.php
```

```sh
php cmdman.phar abc.def.Ghi::foo
php cmdman.phar abc.def.Ghi::bar
```

### パターン3: 単一コマンドとサブコマンドの併用

```
lib/abc/def/Ghi/cmd.php        # abc.def.Ghi
lib/abc/def/Ghi/cmd/foo.php    # abc.def.Ghi::foo
```

## 引数の定義

PHPDocの `@param` アノテーションで引数を宣言します。
宣言された変数はコマンドスクリプト内でそのまま使用できます。

```php
<?php
/**
 * ユーザー情報を表示する
 * @param string $name 名前 @['require'=>true]
 * @param integer $age 年齢 @['init'=>20]
 */
echo $name . ' (' . $age . ')';
```

```sh
php cmdman.phar my.Cmd --name Alice --age 30
# => Alice (30)

php cmdman.phar my.Cmd --name Bob
# => Bob (20)       ← age のデフォルト値が使われる

php cmdman.phar my.Cmd
# => エラー          ← name は必須
```

### 型

| 型 | 説明 |
|---|---|
| `string` | 文字列 |
| `integer` | 整数 |
| `float` | 浮動小数点数 |
| `boolean` | 真偽値 (`true` / `false`) |

型の末尾に `[]` を付けると、同名オプションの複数指定を配列として受け取れます。

```php
<?php
/**
 * @param string[] $file ファイルパス
 */
var_dump($file);
```

```sh
php cmdman.phar my.Cmd --file a.txt --file b.txt --file c.txt
# => array('a.txt', 'b.txt', 'c.txt')
```

### アノテーション

`@param` の説明文末尾に `@[...]` 形式で指定します。

| キー | 型 | 説明 |
|---|---|---|
| `require` | boolean | `true` にすると必須パラメータになる |
| `init` | mixed | 未指定時のデフォルト値 |

```php
<?php
/**
 * @param string $host ホスト名 @['require'=>true]
 * @param integer $port ポート番号 @['init'=>8080]
 * @param boolean $ssl SSL有効化 @['init'=>false]
 */
```

## 特殊スクリプトファイル (フック)

`cmd/` ディレクトリ内に配置すると自動的に実行されます。

| ファイル | タイミング |
|---|---|
| `__setup__.php` | コマンド実行前 |
| `__teardown__.php` | コマンド実行後 |
| `__exception__.php` | コマンド実行中に例外が発生した時 |

```
lib/abc/def/Ghi/cmd/
    __setup__.php       # 前処理
    __teardown__.php    # 後処理
    __exception__.php   # 例外処理
    foo.php
    bar.php
```

`__setup__.php` と `__teardown__.php` は同ディレクトリ内の全サブコマンドに適用されます。

## エラーコールバック

例外発生時に呼び出される関数を定義できます。

```php
define('CMDMAN_ERROR_CALLBACK', '\\myapp\\Log::error');
```

または実行時に `--error-callback` オプションで指定します。

```sh
php cmdman.phar my.Cmd --error-callback "\\myapp\\Log::error"
```

## 標準入出力 (`\cmdman\Std`)

### 色付き出力

```php
\cmdman\Std::println_default($msg);    // デフォルト
\cmdman\Std::println_primary($msg);    // 青
\cmdman\Std::println_success($msg);    // 緑
\cmdman\Std::println_info($msg);       // シアン
\cmdman\Std::println_warning($msg);    // 黄
\cmdman\Std::println_danger($msg);     // 赤
```

### ユーザー入力

```php
// 基本的な入力
$name = \cmdman\Std::read('名前を入力');

// デフォルト値付き
$name = \cmdman\Std::read('名前を入力', 'Guest');

// 選択式
$answer = \cmdman\Std::read('続行しますか？', 'yes', ['yes', 'no']);

// 複数行入力 (行頭に . で終了)
$text = \cmdman\Std::read('本文を入力', null, [], true);

// パスワード入力 (入力を非表示)
$password = \cmdman\Std::silently('パスワード');
```

## ファイル操作 (`\cmdman\Util`)

```php
// 読み込み・書き込み・追記
\cmdman\Util::file_read('/path/to/file');
\cmdman\Util::file_write('/path/to/file', '内容');
\cmdman\Util::file_append('/path/to/file', '追記内容');

// ディレクトリ作成
\cmdman\Util::mkdir('/path/to/dir');

// コピー (ディレクトリの場合は再帰的にコピー)
\cmdman\Util::copy('/src', '/dest');

// 移動
\cmdman\Util::mv('/src', '/dest');

// 削除 (ディレクトリの場合は再帰的に削除)
\cmdman\Util::rm('/path/to/target');

// ファイル一覧
\cmdman\Util::ls('/path/to/dir');                         // 直下のみ
\cmdman\Util::ls('/path/to/dir', true);                   // 再帰的
\cmdman\Util::ls('/path/to/dir', true, '/\.php$/');       // パターン指定
```

## 並列処理 (`\cmdman\Util::pctrl`)

`pcntl` 拡張が必要です。データの数だけプロセスをフォークして並列処理します。

```php
\cmdman\Util::pctrl(function($item, $key){
    // 子プロセスで実行される
    echo $item . PHP_EOL;
}, ['task1', 'task2', 'task3']);
```

## 終了制御

```php
\cmdman\Util::exit_error();   // エラーとして終了 (exit code: 1)
\cmdman\Util::exit_wait();    // 一時停止として終了 (exit code: 19)
```

`exit_wait()` は `cmdman.Util::repeat` コマンドと組み合わせて使います。

## 組み込みコマンド

### Pharアーカイブの作成

```sh
php -d phar.readonly=0 cmdman.phar cmdman.Util::archive --dir src/
php -d phar.readonly=0 cmdman.phar cmdman.Util::archive --dir src/ --out output.phar
```

### Pharアーカイブの展開

```sh
php cmdman.phar cmdman.Util::extract --file library.phar
php cmdman.phar cmdman.Util::extract --file library.phar --out ./output/
```

### コマンドの繰り返し実行

```sh
# 60秒間隔で繰り返し実行
php cmdman.phar cmdman.Util::repeat --cmd "my.Cmd --name test"

# 待ち時間を指定 (秒)
php cmdman.phar cmdman.Util::repeat --cmd "my.Cmd" --wt 120

# デーモン化 (PIDファイルで管理)
php cmdman.phar cmdman.Util::repeat --cmd "my.Cmd" --daemon /tmp/mycmd

# ログ出力
php cmdman.phar cmdman.Util::repeat --cmd "my.Cmd" --log /var/log/mycmd.log

# エラー時も強制続行
php cmdman.phar cmdman.Util::repeat --cmd "my.Cmd" --force
```

コマンドが `exit_wait()` で終了した場合、`--wt` で指定した時間待機してから再実行します。
コマンドがエラー終了した場合、`--force` を指定していなければ繰り返しを停止します。

## ビルド

```sh
php -d phar.readonly=0 cmdman.php cmdman.Util::archive --dir src/
```
