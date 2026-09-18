-- =====================================================================
-- (任意) Excel から移行した品目にカテゴリの初期案を一括設定する
-- 根拠: docs/02_全行一覧.md の「仮カテゴリ」列(解析時の案)。設定後に画面で自由に変更できます。
-- 対象: カテゴリ未設定(NULL)の移行品目のみ。すでに設定済みの品目は変更しません。
-- 実行しない場合は、品目編集画面で 1 件ずつカテゴリを選ぶ必要があります(品目編集ではカテゴリ必須のため)。
-- =====================================================================
SET NAMES utf8mb4;

-- PC本体 (4 件)
UPDATE inventory_items SET category_id = 1, updated_by = 'category_init' WHERE category_id IS NULL AND source_excel_rows IN ('4;5', '61', '62', '67');

-- その他 (1 件)
UPDATE inventory_items SET category_id = 12, updated_by = 'category_init' WHERE category_id IS NULL AND source_excel_rows IN ('6');

-- 入力機器 (15 件)
UPDATE inventory_items SET category_id = 3, updated_by = 'category_init' WHERE category_id IS NULL AND source_excel_rows IN ('7', '8', '9', '10', '16', '50', '51', '52', '54', '55', '93', '94', '95', '96', '97');

-- ストレージ・メディア (9 件)
UPDATE inventory_items SET category_id = 5, updated_by = 'category_init' WHERE category_id IS NULL AND source_excel_rows IN ('11', '12', '13', '18', '26', '27', '28', '60', '114');

-- 周辺機器 (11 件)
UPDATE inventory_items SET category_id = 4, updated_by = 'category_init' WHERE category_id IS NULL AND source_excel_rows IN ('14', '15', '17', '21', '24', '25', '33', '63', '65', '66', '92');

-- ケーブル・変換アダプタ (21 件)
UPDATE inventory_items SET category_id = 7, updated_by = 'category_init' WHERE category_id IS NULL AND source_excel_rows IN ('19', '20', '22', '23', '68', '69', '70', '71', '74', '75', '77', '84', '85', '86', '87', '88', '91', '99', '100', '101', '102');

-- 工具・測定器 (6 件)
UPDATE inventory_items SET category_id = 9, updated_by = 'category_init' WHERE category_id IS NULL AND source_excel_rows IN ('29', '30', '32', '43', '44', '47');

-- ネットワーク機器 (10 件)
UPDATE inventory_items SET category_id = 6, updated_by = 'category_init' WHERE category_id IS NULL AND source_excel_rows IN ('31', '34;35', '38', '39', '40', '41', '42', '49', '53', '98');

-- 設置・固定器具 (1 件)
UPDATE inventory_items SET category_id = 11, updated_by = 'category_init' WHERE category_id IS NULL AND source_excel_rows IN ('36');

-- 電源関連 (25 件)
UPDATE inventory_items SET category_id = 8, updated_by = 'category_init' WHERE category_id IS NULL AND source_excel_rows IN ('37', '56', '64', '72', '73', '76', '78', '79', '80', '81', '82', '83', '89', '90', '103', '104', '105', '106', '107', '108', '109', '110', '111', '112', '113');

-- 配線資材・消耗品 (5 件)
UPDATE inventory_items SET category_id = 10, updated_by = 'category_init' WHERE category_id IS NULL AND source_excel_rows IN ('45', '46', '57', '58', '59');

-- ディスプレイ (1 件)
UPDATE inventory_items SET category_id = 2, updated_by = 'category_init' WHERE category_id IS NULL AND source_excel_rows IN ('48');
