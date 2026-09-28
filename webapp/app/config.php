<?php
// =====================================================================
// 設定ファイル(初期値)
// ★ サーバーの接続情報はこのファイルではなく、同じフォルダの config.local.php に書いてください。
//   config.local.php.example をコピーして config.local.php を作り、値を書き換えます。
//   config.local.php はプログラム更新(上書きアップロード)の対象外なので、更新のたびに消えません。
// XServer サーバーパネル > MySQL設定 で確認できます
//   host : MySQL ホスト名(例 mysql1234.xserver.jp)
//   name : データベース名(例 xxxxx_nkc)
//   user : ユーザー名(例 xxxxx_nkc)
//   pass : パスワード
// =====================================================================
return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'nkc_inventory',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name'         => 'NKC 在庫管理システム',
        'session_name' => 'nkc_inventory_sid',
        'timezone'     => 'Asia/Tokyo',
        // ログインしないまま操作できる時間(秒)。0 なら制限なし
        'session_lifetime' => 8 * 60 * 60,
    ],
];
