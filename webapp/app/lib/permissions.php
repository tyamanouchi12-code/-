<?php
// 権限コードの定義。利用者ごとに user_permissions テーブルで付与する。
// ここに追加すれば利用者管理画面のチェックボックスにも自動で表示される。

function permission_definitions(): array
{
    return [
        'count.confirm'   => '棚卸の締め・締め解除・削除',
        'item.deactivate' => 'カテゴリ・品名の無効化・削除',
        'master.manage'   => 'マスタ管理(保管場所・客先・状態)',
        'user.manage'     => '利用者管理(利用者の追加・権限設定・パスワード再設定)',
    ];
}
