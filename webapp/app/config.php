<?php
// =====================================================================
// 設定ファイル  ※アップロード前に XServer のデータベース情報に書き換えてください
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
        'name'         => 'NKC 棚卸システム',
        'session_name' => 'nkc_inventory_sid',
        'timezone'     => 'Asia/Tokyo',
        // ログインしないまま操作できる時間(秒)。0 なら制限なし
        'session_lifetime' => 8 * 60 * 60,
    ],
];
