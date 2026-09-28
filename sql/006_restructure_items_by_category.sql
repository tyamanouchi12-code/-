-- =====================================================================
-- 品目をカテゴリ単位にまとめ、これまでの品目を「内訳(個体・型番)」に変換する(A案)
--   カテゴリ(例: 電源関連) = 品目(例: 電源関連)
--   内訳(例: ACアダプタ：TOSHIBA PA3755U、LANケーブル(大)、デスクトップ本体 NKC：0006)
--   管理No のある内訳は「個体(1台)」、無い内訳は「数量で数える種類」。数量は棚卸の結果として持つ
-- 実行前提: 001〜004 実行済み(005 でカテゴリを設定していない品目は「その他」に入ります)
-- 実行後は元に戻せません。実行前に phpMyAdmin の「エクスポート」でバックアップを取ってください。
-- =====================================================================
SET NAMES utf8mb4;

-- 0. 内訳テーブル(inventory_units)に列を追加
ALTER TABLE inventory_units
  ADD COLUMN name              VARCHAR(200) NULL            AFTER item_id,     -- 内訳名(旧: 品名)
  ADD COLUMN condition_code    VARCHAR(20)  NULL            AFTER name,        -- 状態(新品/中古)
  ADD COLUMN manufacturer      VARCHAR(100) NULL            AFTER ip_address,
  ADD COLUMN model_number      VARCHAR(100) NULL            AFTER manufacturer,
  ADD COLUMN customer_id       INT          NULL            AFTER model_number,
  ADD COLUMN sort_order        INT          NOT NULL DEFAULT 0 AFTER is_active,
  ADD COLUMN source_excel_rows VARCHAR(50)  NULL            AFTER source_excel_row,
  ADD COLUMN legacy_item_id    INT          NULL            AFTER source_excel_rows,  -- 変換前の品目ID(追跡用)
  ADD KEY idx_units_legacy (legacy_item_id),
  ADD CONSTRAINT fk_units_condition FOREIGN KEY (condition_code) REFERENCES conditions (code),
  ADD CONSTRAINT fk_units_customer  FOREIGN KEY (customer_id)    REFERENCES customers (id);

ALTER TABLE inventory_count_unit_results
  ADD COLUMN counted_quantity INT NULL AFTER result;     -- 内訳ごとの棚卸数量

-- 1. カテゴリ未設定の品目は「その他」へ
UPDATE inventory_items SET category_id = (SELECT id FROM categories WHERE name = 'その他' LIMIT 1) WHERE category_id IS NULL;

-- 2. 変換前の品目IDを控える
CREATE TEMPORARY TABLE _old_items AS SELECT id, category_id, sort_order FROM inventory_items;

-- 3. カテゴリごとの新しい品目を作る
INSERT INTO inventory_items (item_code, item_name, management_type, category_id, stock_type, is_active, sort_order, created_by, updated_by, notes)
SELECT CONCAT('CAT-', c.id), c.name, 'unit', c.id, 'internal', c.is_active, c.sort_order, 'restructure', 'restructure', NULL
FROM categories c
WHERE EXISTS (SELECT 1 FROM _old_items o WHERE o.category_id = c.id);

CREATE TEMPORARY TABLE _new_items AS SELECT id, category_id FROM inventory_items WHERE item_code LIKE 'CAT-%';

-- 4a. 既存の個体(管理Noあり)に内訳の情報を写し、新しい品目にぶら下げ直す
UPDATE inventory_units u
JOIN inventory_items old ON old.id = u.item_id
JOIN _new_items n ON n.category_id = old.category_id
SET u.name = old.item_name,
    u.condition_code = old.condition_code,
    u.manufacturer = old.manufacturer,
    u.model_number = old.model_number,
    u.customer_id = old.customer_id,
    u.location_id = COALESCE(u.location_id, old.location_id),
    u.sort_order = old.sort_order * 10,
    u.source_excel_rows = old.source_excel_rows,
    u.legacy_item_id = old.id,
    u.item_id = n.id;

-- 4b. 個体を持たない品目(数量管理など)を内訳の行にする
INSERT INTO inventory_units (item_id, name, condition_code, management_no, serial_number, ip_address, manufacturer, model_number, customer_id,
                             status, location_id, notes, is_active, sort_order, source_excel_rows, legacy_item_id, created_by, updated_by)
SELECT n.id, old.item_name, old.condition_code, NULL, old.serial_number, old.network_info, old.manufacturer, old.model_number, old.customer_id,
       'in_stock', old.location_id, old.notes, old.is_active, old.sort_order * 10, old.source_excel_rows, old.id, 'restructure', 'restructure'
FROM inventory_items old
JOIN _old_items o ON o.id = old.id
JOIN _new_items n ON n.category_id = old.category_id
WHERE NOT EXISTS (SELECT 1 FROM inventory_units u WHERE u.legacy_item_id = old.id);

-- 4c. 個体を持つ品目の備考は品目にしかなかったので、内訳の備考が空なら引き継ぐ
UPDATE inventory_units u JOIN inventory_items old ON old.id = u.legacy_item_id
SET u.notes = old.notes WHERE (u.notes IS NULL OR u.notes = '') AND old.notes IS NOT NULL AND old.management_type = 'unit';

-- 5. 棚卸の内訳結果
--   既存の個体結果: 有=1 / 無=0 / 未確認=NULL
UPDATE inventory_count_unit_results SET counted_quantity = CASE result WHEN 'present' THEN 1 WHEN 'absent' THEN 0 ELSE NULL END;
--   数量管理だった品目の明細 → その内訳行の結果へ
INSERT INTO inventory_count_unit_results (count_id, unit_id, result, counted_quantity, notes, created_by, updated_by, created_at)
SELECT d.count_id, u.id,
       CASE WHEN d.count_quantity IS NULL THEN 'unchecked' WHEN d.count_quantity > 0 THEN 'present' ELSE 'absent' END,
       d.count_quantity, d.notes, d.created_by, d.updated_by, d.created_at
FROM inventory_count_details d
JOIN inventory_units u ON u.legacy_item_id = d.item_id AND u.management_no IS NULL
JOIN inventory_items old ON old.id = d.item_id AND old.management_type <> 'unit';

-- 6. 新しい品目の棚卸明細(内訳の合計)。担当者・日時は旧明細から引き継ぐ
INSERT INTO inventory_count_details (count_id, item_id, count_quantity, confirm_status, location_id_at_count, counted_by, counted_at, notes, created_by, updated_by)
SELECT r.count_id, u.item_id,
       CASE WHEN SUM(r.counted_quantity IS NOT NULL) = 0 THEN NULL ELSE SUM(COALESCE(r.counted_quantity, 0)) END,
       CASE WHEN SUM(r.counted_quantity IS NOT NULL) = 0 THEN 'unconfirmed' ELSE 'confirmed' END,
       NULL, MAX(od.counted_by), MAX(od.counted_at), NULL, 'restructure', 'restructure'
FROM inventory_count_unit_results r
JOIN inventory_units u ON u.id = r.unit_id
LEFT JOIN inventory_count_details od ON od.count_id = r.count_id AND od.item_id = u.legacy_item_id
GROUP BY r.count_id, u.item_id;

-- 7. 持ち出し記録を新しい構造に付け替える(内訳が無い持ち出しは旧品目の内訳行へ)
UPDATE item_checkouts c JOIN inventory_units u ON u.legacy_item_id = c.item_id AND u.management_no IS NULL
SET c.unit_id = u.id WHERE c.unit_id IS NULL;
UPDATE item_checkouts c JOIN inventory_units u ON u.id = c.unit_id SET c.item_id = u.item_id;

-- 8. 旧品目の明細と旧品目を削除(情報は内訳行の legacy_item_id / source_excel_rows に残る)
DELETE d FROM inventory_count_details d JOIN _old_items o ON o.id = d.item_id;
DELETE i FROM inventory_items i JOIN _old_items o ON o.id = i.id;

-- 9. 品目コードを振り直し(ITEM-000001 …)、内訳の表示順を整える
SET @n := 0;
UPDATE inventory_items SET item_code = CONCAT('ITEM-', LPAD((@n := @n + 1), 6, '0')) WHERE item_code LIKE 'CAT-%' ORDER BY sort_order, id;

DROP TEMPORARY TABLE _old_items;
DROP TEMPORARY TABLE _new_items;

-- 確認用
SELECT (SELECT COUNT(*) FROM inventory_items) AS items, (SELECT COUNT(*) FROM inventory_units) AS units,
       (SELECT COUNT(*) FROM inventory_count_details) AS details, (SELECT COUNT(*) FROM inventory_count_unit_results) AS unit_results;
