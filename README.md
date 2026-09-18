# NKC 棚卸 Web システム

現行の Excel 棚卸表「（仮）NKC棚卸.xlsx」の**シート1**を Web システム化するプロジェクトです(他のシートは対象外)。

## 現在の状態

**フェーズ4: Webアプリ完成(検証済み)。XServer への設置待ち**。設置手順は `docs/07_XServer導入手順.md`。

| ファイル | 内容 |
|---|---|
| `docs/01_現行Excel解析.md` | 解析結果、表記揺れ、移行要確認データ(Q-01〜Q-14)、カラム対応表、設計判断(D-01〜D-06) |
| `docs/03_移行ルール.md` | ご回答を反映した移行ルール(確定分)と未確定事項 |
| `webapp/` | Webアプリ本体(PHP 8.1+ / MySQL)。このフォルダの中身を XServer にアップロード |
| `docs/07_XServer導入手順.md` | XServer への設置手順(DB作成 → SQL実行 → config.php 編集 → FFFTP → setup.php) |
| `docs/screenshots/` | 画面イメージ |
| `sql/001_schema.sql` 〜 `003_migrate_excel.sql` | DB作成・マスタ投入・Excel移行データ投入のSQL(phpMyAdmin で順に実行) |
| `docs/06_アプリ画面構成案.md` | Webアプリの技術方針・画面一覧・確認事項 |
| `docs/04_テーブル定義.md` | B案のテーブル定義とDDL案(MySQL 8.0 仮定) |
| `docs/05_移行データ.md` | 移行データの件数・Excelとの突合結果 |
| `docs/migration/` | 移行データ生成スクリプトと出力CSV |
| `docs/02_全行一覧.md` | シート1全111行の原本一覧(結合セル展開済み)と仮カテゴリ・仮管理方法 |
| `docs/source/（仮）NKC棚卸.xlsx` | 解析対象の現行Excel(基準資料) |
| `docs/source/excel_raw_extract.csv` | シート1から機械抽出した生データ(UTF-8 BOM付き) |

## Webアプリの構成

- 素の PHP 8.1+(フレームワーク・Composer なし)、PDO(MySQL)、HTML/CSS/JavaScript。PC 用。
- `webapp/index.php?page=...` がすべての画面の入口。`webapp/app/` は `.htaccess` で直接アクセス禁止。
- 権限は利用者ごとに設定(`webapp/app/lib/permissions.php`): 棚卸の確定 / 品目・個体の無効化 / マスタ管理 / 利用者管理。
- ローカルでの動作確認: MariaDB に `sql/*.sql` を投入し、`webapp/app/config.php` を書き換えて `php -S 127.0.0.1:8080 -t webapp`。
