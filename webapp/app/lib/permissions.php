<?php
// 権限コードの定義。利用者ごとに user_permissions テーブルで付与する。
// ここに追加すれば利用者管理画面のチェックボックスにも自動で表示される。

function permission_definitions(): array
{
    return [
        'count.confirm'   => '棚卸の確定・確定解除',
        'item.deactivate' => '品目・個体の無効化・再有効化',
        'master.manage'   => 'マスタ管理(保管場所・カテゴリ・客先・状態)',
        'user.manage'     => '利用者管理(利用者の追加・権限設定・パスワード再設定)',
    ];
}
