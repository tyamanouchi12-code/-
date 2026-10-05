-- =====================================================================
-- 管理No を「ユニークな数字」に変更する
--   1. 数え方(count_mode)列を追加。今まで管理No が入っていた品名は「1台ずつ(有/無)」、それ以外は「個数」
--   2. 今まで入っていた管理No(NKC：0006 など)は備考の末尾に「旧管理No: …」として追記する(既存の備考は消さない)
--   3. 全ての品名に 1 から始まる連番を振り直す(カテゴリの表示順 → 品名の表示順 の順)
--   4. 管理No に重複禁止(ユニーク)の制約を付ける
-- 実行前提: 001〜007 実行済み。MariaDB 10.2 以降 / MySQL 8.0 以降(XServer は対応済み)
-- 実行後は元に戻せません。実行前に phpMyAdmin の「エクスポート」でバックアップを取ってください。
-- =====================================================================
SET NAMES utf8mb4;

-- 1. 数え方
ALTER TABLE inventory_units
  ADD COLUMN count_mode VARCHAR(20) NOT NULL DEFAULT 'quantity' AFTER condition_code;   -- 'quantity'(個数で数える) / 'single'(1台ずつ・有/無)
UPDATE inventory_units SET count_mode = 'single'
 WHERE management_no IS NOT NULL AND TRIM(management_no) <> '';

-- 2. 旧管理No を備考へ追記(上書きしない)
UPDATE inventory_units
   SET notes = CASE
                 WHEN notes IS NULL OR TRIM(notes) = '' THEN CONCAT('旧管理No: ', TRIM(management_no))
                 ELSE CONCAT(notes, '\n旧管理No: ', TRIM(management_no))
               END
 WHERE management_no IS NOT NULL AND TRIM(management_no) <> '';

-- 3. 管理No を数字の列に変えて連番を振る
UPDATE inventory_units SET management_no = NULL;
ALTER TABLE inventory_units DROP INDEX idx_units_management_no;
ALTER TABLE inventory_units MODIFY management_no INT UNSIGNED NULL;
UPDATE inventory_units u
  JOIN (SELECT u2.id, ROW_NUMBER() OVER (ORDER BY i.sort_order, i.id, u2.sort_order, u2.id) AS rn
          FROM inventory_units u2 JOIN inventory_items i ON i.id = u2.item_id) r ON r.id = u.id
   SET u.management_no = r.rn;

-- 4. 必須・重複禁止
ALTER TABLE inventory_units
  MODIFY management_no INT UNSIGNED NOT NULL,
  ADD UNIQUE KEY uq_units_management_no (management_no);

-- 確認用
SELECT COUNT(*) AS units, MIN(management_no) AS min_no, MAX(management_no) AS max_no,
       SUM(count_mode = 'single') AS single_units, SUM(notes LIKE '%旧管理No: %') AS notes_with_old_no
  FROM inventory_units;
