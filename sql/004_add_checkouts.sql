-- =====================================================================
-- 追加: 備品の持ち出し・戻し(item_checkouts)
-- すでに 001_schema.sql を実行済みの環境では、このファイルだけを phpMyAdmin で実行してください。
-- (001_schema.sql にも同じテーブル定義を含めてあるため、新規構築では不要です)
-- =====================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS item_checkouts (
  id                   INT           NOT NULL AUTO_INCREMENT,
  item_id              INT           NOT NULL,                       -- 持ち出した品目
  unit_id              INT           NULL,                           -- 個体管理品の場合の個体
  quantity             INT           NOT NULL DEFAULT 1,             -- 持ち出し数量(個体は 1)
  checked_out_user_id  INT           NULL,                           -- 持ち出した人(利用者。未選択可)
  checked_out_by_name  VARCHAR(100)  NULL,                           -- 持ち出した人の名前(利用者以外も入力可)
  checked_out_at       DATETIME      NOT NULL,                       -- 持ち出し日時
  returned_at          DATETIME      NULL,                           -- 戻し日時(NULL = 持ち出し中)
  returned_by_name     VARCHAR(100)  NULL,                           -- 戻しを登録した人
  notes                VARCHAR(500)  NULL,                           -- メモ(持ち出し先など)
  created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by           VARCHAR(100)  NULL,                           -- 登録操作をした利用者
  updated_by           VARCHAR(100)  NULL,
  PRIMARY KEY (id),
  KEY idx_checkouts_item (item_id, returned_at),
  KEY idx_checkouts_unit (unit_id, returned_at),
  KEY idx_checkouts_open (returned_at, checked_out_at),
  CONSTRAINT fk_checkouts_item FOREIGN KEY (item_id) REFERENCES inventory_items (id),
  CONSTRAINT fk_checkouts_unit FOREIGN KEY (unit_id) REFERENCES inventory_units (id),
  CONSTRAINT fk_checkouts_user FOREIGN KEY (checked_out_user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
