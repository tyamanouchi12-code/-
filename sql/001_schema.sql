-- =====================================================================
-- NKC 棚卸 Web システム  スキーマ定義
-- 対象: MySQL 8.0 / MariaDB 10.x (XServer)
-- 文字コード: utf8mb4 (全角コロン・全角スペース・「⇔」等を含むため)
-- 実行方法: phpMyAdmin の「SQL」タブに貼り付けて実行(データベースを選択した状態で)
-- 実行順: 001_schema.sql → 002_seed_master.sql → 003_migrate_excel.sql
-- (004_add_checkouts.sql は 001 実行後に持ち出し機能を追加した環境向け。新規構築では不要)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS item_checkouts;
DROP TABLE IF EXISTS inventory_count_unit_results;
DROP TABLE IF EXISTS inventory_count_details;
DROP TABLE IF EXISTS inventory_counts;
DROP TABLE IF EXISTS inventory_units;
DROP TABLE IF EXISTS inventory_items;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS locations;
DROP TABLE IF EXISTS conditions;
DROP TABLE IF EXISTS user_permissions;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 0. 利用者(ログイン)と権限
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id             INT          NOT NULL AUTO_INCREMENT,
  login_id       VARCHAR(50)  NOT NULL,                         -- ログインID
  display_name   VARCHAR(100) NOT NULL,                         -- 表示名(登録者・更新者に記録する名前)
  password_hash  VARCHAR(255) NOT NULL,                         -- PHP password_hash() の値
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  last_login_at  DATETIME     NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_login_id (login_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 利用者ごとの権限(権限コードは webapp/app/lib/permissions.php で定義)
--   count.confirm   棚卸の確定・確定解除
--   item.deactivate 品目・個体の無効化/再有効化
--   master.manage   マスタ管理(保管場所・カテゴリ・客先・状態)
--   user.manage     利用者管理(追加・権限設定・パスワード再設定)
CREATE TABLE user_permissions (
  user_id          INT          NOT NULL,
  permission_code  VARCHAR(50)  NOT NULL,
  PRIMARY KEY (user_id, permission_code),
  CONSTRAINT fk_user_permissions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 1. 状態マスタ(新品 / 中古。将来: 不良 / 修理中 / 廃棄予定 を追加可能)
-- ---------------------------------------------------------------------
CREATE TABLE conditions (
  code        VARCHAR(20)  NOT NULL,
  name        VARCHAR(50)  NOT NULL,
  sort_order  INT          NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. 保管場所マスタ
-- ---------------------------------------------------------------------
CREATE TABLE locations (
  id          INT          NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100) NOT NULL,
  sort_order  INT          NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_locations_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. カテゴリマスタ
-- ---------------------------------------------------------------------
CREATE TABLE categories (
  id          INT          NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100) NOT NULL,
  sort_order  INT          NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. 客先マスタ
-- ---------------------------------------------------------------------
CREATE TABLE customers (
  id          INT          NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100) NOT NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. 品目マスタ
-- ---------------------------------------------------------------------
CREATE TABLE inventory_items (
  id                INT           NOT NULL AUTO_INCREMENT,           -- 内部ID(主キー)
  item_code         VARCHAR(20)   NOT NULL,                          -- 品目コード ITEM-000001 (QR/バーコード用)
  item_name         VARCHAR(200)  NULL,                              -- 品名(原文)。移行データに空欄1件(Excel 73行目)があるため NULL 許可。画面では必須
  management_type   VARCHAR(20)   NOT NULL DEFAULT 'quantity',       -- 'quantity'(数量管理) / 'unit'(個体管理)
  category_id       INT           NULL,
  condition_code    VARCHAR(20)   NULL,                              -- 状態(空欄は NULL)
  stock_type        VARCHAR(20)   NOT NULL DEFAULT 'internal',       -- 在庫区分 'internal'(社内在庫) / 'customer_surplus'(客先備品余り)
  customer_id       INT           NULL,
  manufacturer      VARCHAR(100)  NULL,                              -- メーカー(任意)
  model_number      VARCHAR(100)  NULL,                              -- 型番(任意)
  serial_number     VARCHAR(100)  NULL,                              -- 数量管理品で1台だけの物用。個体管理品は inventory_units 側
  network_info      VARCHAR(255)  NULL,                              -- IPアドレス等(任意)
  location_id       INT           NULL,                              -- 保管場所(空欄は NULL)
  notes             TEXT          NULL,                              -- 備考(品目そのものに関するメモ)
  is_active         TINYINT(1)    NOT NULL DEFAULT 1,                -- 有効/無効(物理削除しない)
  sort_order        INT           NOT NULL DEFAULT 0,                -- 表示順
  created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by        VARCHAR(100)  NULL,
  updated_by        VARCHAR(100)  NULL,
  source_excel_rows VARCHAR(50)   NULL,                              -- 移行元Excel行(例 '4;5')。検証用
  PRIMARY KEY (id),
  UNIQUE KEY uq_items_code (item_code),
  KEY idx_items_name (item_name),
  KEY idx_items_location (location_id),
  KEY idx_items_category (category_id),
  KEY idx_items_sort (sort_order),
  CONSTRAINT fk_items_category  FOREIGN KEY (category_id)    REFERENCES categories (id),
  CONSTRAINT fk_items_condition FOREIGN KEY (condition_code) REFERENCES conditions (code),
  CONSTRAINT fk_items_customer  FOREIGN KEY (customer_id)    REFERENCES customers (id),
  CONSTRAINT fk_items_location  FOREIGN KEY (location_id)    REFERENCES locations (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. 個体(個体管理品の実物1台ごと)
-- ---------------------------------------------------------------------
CREATE TABLE inventory_units (
  id                INT           NOT NULL AUTO_INCREMENT,
  item_id           INT           NOT NULL,
  management_no     VARCHAR(50)   NULL,                              -- 管理No(文字列。'NKC：0006','238','NKC-OZAKI' 等)
  serial_number     VARCHAR(100)  NULL,
  ip_address        VARCHAR(100)  NULL,
  status            VARCHAR(20)   NOT NULL DEFAULT 'in_stock',       -- 'in_stock'(在庫) / 'lent'(貸出) / 'in_use'(使用中) / 'disposed'(廃棄)
  location_id       INT           NULL,
  notes             TEXT          NULL,
  is_active         TINYINT(1)    NOT NULL DEFAULT 1,
  created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by        VARCHAR(100)  NULL,
  updated_by        VARCHAR(100)  NULL,
  source_excel_row  INT           NULL,
  PRIMARY KEY (id),
  KEY idx_units_item (item_id),
  KEY idx_units_management_no (management_no),
  CONSTRAINT fk_units_item     FOREIGN KEY (item_id)     REFERENCES inventory_items (id),
  CONSTRAINT fk_units_location FOREIGN KEY (location_id) REFERENCES locations (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. 棚卸(1回の棚卸 = 1行)
-- ---------------------------------------------------------------------
CREATE TABLE inventory_counts (
  id            INT           NOT NULL AUTO_INCREMENT,
  count_name    VARCHAR(100)  NOT NULL,                              -- 棚卸名称
  base_date     DATE          NOT NULL,                              -- 棚卸基準日
  count_period  VARCHAR(100)  NULL,                                  -- 棚卸期間(文字列)
  entry_date    DATE          NULL,                                  -- 入力日付
  start_date    DATE          NULL,                                  -- 棚卸開始日
  end_date      DATE          NULL,                                  -- 棚卸終了日
  status        VARCHAR(20)   NOT NULL DEFAULT 'preparing',          -- 'preparing'(準備中) / 'in_progress'(棚卸中) / 'confirmed'(確定済)
  notes         TEXT          NULL,
  created_by    VARCHAR(100)  NULL,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  confirmed_by  VARCHAR(100)  NULL,
  confirmed_at  DATETIME      NULL,
  PRIMARY KEY (id),
  KEY idx_counts_base_date (base_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. 棚卸明細(品目 × 棚卸)
-- ---------------------------------------------------------------------
CREATE TABLE inventory_count_details (
  id                    INT           NOT NULL AUTO_INCREMENT,
  count_id              INT           NOT NULL,
  item_id               INT           NOT NULL,
  count_quantity        INT           NULL,                          -- 棚卸数量(未入力は NULL)
  confirm_status        VARCHAR(20)   NOT NULL DEFAULT 'unconfirmed',-- 'unconfirmed'(未確認) / 'confirmed'(確認済) / 'needs_check'(要確認)
  location_id_at_count  INT           NULL,                          -- 棚卸時保管場所
  counted_by            VARCHAR(100)  NULL,                          -- 棚卸担当者
  counted_at            DATETIME      NULL,                          -- 棚卸日時
  notes                 TEXT          NULL,                          -- 明細備考(その棚卸だけのメモ)
  created_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by            VARCHAR(100)  NULL,
  updated_by            VARCHAR(100)  NULL,
  source_excel_rows     VARCHAR(50)   NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_details_count_item (count_id, item_id),
  KEY idx_details_item (item_id),
  CONSTRAINT fk_details_count    FOREIGN KEY (count_id)             REFERENCES inventory_counts (id),
  CONSTRAINT fk_details_item     FOREIGN KEY (item_id)              REFERENCES inventory_items (id),
  CONSTRAINT fk_details_location FOREIGN KEY (location_id_at_count) REFERENCES locations (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. 棚卸個体結果(個体 × 棚卸)
-- ---------------------------------------------------------------------
CREATE TABLE inventory_count_unit_results (
  id                INT           NOT NULL AUTO_INCREMENT,
  count_id          INT           NOT NULL,
  unit_id           INT           NOT NULL,
  result            VARCHAR(20)   NOT NULL DEFAULT 'unchecked',      -- 'unchecked'(未確認) / 'present'(有) / 'absent'(無)
  notes             TEXT          NULL,
  created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by        VARCHAR(100)  NULL,
  updated_by        VARCHAR(100)  NULL,
  source_excel_row  INT           NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_unit_results_count_unit (count_id, unit_id),
  KEY idx_unit_results_unit (unit_id),
  CONSTRAINT fk_unit_results_count FOREIGN KEY (count_id) REFERENCES inventory_counts (id),
  CONSTRAINT fk_unit_results_unit  FOREIGN KEY (unit_id)  REFERENCES inventory_units (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. 備品の持ち出し・戻し
-- ---------------------------------------------------------------------
CREATE TABLE item_checkouts (
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
