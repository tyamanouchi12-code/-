-- =====================================================================
-- 管理No を全て空にし、「管理Noで管理する」品名にだけ番号を振る方式に変更
--   ・008 で振った連番(1〜)を全て空にする(旧管理No を追記した備考はそのまま)
--   ・管理No は空を許可。重複禁止(ユニーク)の制約は残す(空は重複扱いにならない)
--   ・番号は画面で「管理Noで管理する」にチェックを入れた品名に、4桁(0001〜)で自動採番
-- 実行前提: 008 実行済み
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE inventory_units MODIFY management_no INT UNSIGNED NULL;
UPDATE inventory_units SET management_no = NULL;

-- 確認用(units = 品名数、with_no = 0 なら完了)
SELECT COUNT(*) AS units, SUM(management_no IS NOT NULL) AS with_no FROM inventory_units;
