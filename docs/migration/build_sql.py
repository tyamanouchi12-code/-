# -*- coding: utf-8 -*-
"""
docs/migration/*.csv → sql/002_seed_master.sql, sql/003_migrate_excel.sql を生成する
実行: python3 docs/migration/build_migration_data.py && python3 docs/migration/build_sql.py
"""
import csv, os

BASE = os.path.dirname(os.path.abspath(__file__))
SQL_DIR = os.path.join(BASE, '..', '..', 'sql')

def load(name):
    with open(os.path.join(BASE, name), encoding='utf-8-sig', newline='') as fp:
        return list(csv.DictReader(fp))

def q(v):
    """SQL リテラル化。'' は NULL。数値列も文字列として渡されるので呼び出し側で判断"""
    if v is None or v == '':
        return 'NULL'
    return "'" + str(v).replace('\\', '\\\\').replace("'", "''").replace('\n', '\\n') + "'"

def n(v):
    return 'NULL' if v in (None, '') else str(int(v))

def insert(fp, table, cols, rows, conv):
    if not rows:
        fp.write(f'-- {table}: 0 rows\n\n')
        return
    fp.write(f'-- {table}: {len(rows)} rows\n')
    fp.write(f'INSERT INTO {table} ({", ".join(cols)}) VALUES\n')
    vals = [ '(' + ', '.join(conv[c](r[c]) for c in cols) + ')' for r in rows ]
    fp.write(',\n'.join(vals) + ';\n\n')

AUDIT = {'created_at': lambda v: 'NOW()', 'updated_at': lambda v: 'NOW()', 'created_by': q, 'updated_by': q}

with open(os.path.join(SQL_DIR, '002_seed_master.sql'), 'w', encoding='utf-8') as fp:
    fp.write('-- マスタ初期データ(状態 / 保管場所 / カテゴリ)。客先マスタは移行時は空。\nSET NAMES utf8mb4;\n\n')
    insert(fp, 'conditions', ['code', 'name', 'sort_order', 'is_active'], load('conditions.csv'),
           {'code': q, 'name': q, 'sort_order': n, 'is_active': n})
    insert(fp, 'locations', ['id', 'name', 'sort_order', 'is_active'], load('locations.csv'),
           {'id': n, 'name': q, 'sort_order': n, 'is_active': n})
    insert(fp, 'categories', ['id', 'name', 'sort_order', 'is_active'], load('categories.csv'),
           {'id': n, 'name': q, 'sort_order': n, 'is_active': n})
    insert(fp, 'customers', ['id', 'name', 'is_active'], load('customers.csv'), {'id': n, 'name': q, 'is_active': n})

with open(os.path.join(SQL_DIR, '003_migrate_excel.sql'), 'w', encoding='utf-8') as fp:
    fp.write('-- 現行Excel「（仮）NKC棚卸.xlsx」シート1 からの移行データ(docs/migration/*.csv より生成)\nSET NAMES utf8mb4;\n\n')
    cols = ['id', 'item_code', 'item_name', 'management_type', 'category_id', 'condition_code', 'stock_type', 'customer_id',
            'manufacturer', 'model_number', 'serial_number', 'network_info', 'location_id', 'notes', 'is_active', 'sort_order',
            'created_at', 'updated_at', 'created_by', 'updated_by', 'source_excel_rows']
    conv = {'id': n, 'item_code': q, 'item_name': q, 'management_type': q, 'category_id': n, 'condition_code': q,
            'stock_type': q, 'customer_id': n, 'manufacturer': q, 'model_number': q, 'serial_number': q, 'network_info': q,
            'location_id': n, 'notes': q, 'is_active': n, 'sort_order': n, 'source_excel_rows': q, **AUDIT}
    insert(fp, 'inventory_items', cols, load('inventory_items.csv'), conv)

    cols = ['id', 'item_id', 'management_no', 'serial_number', 'ip_address', 'status', 'location_id', 'notes', 'is_active',
            'created_at', 'updated_at', 'created_by', 'updated_by', 'source_excel_row']
    conv = {'id': n, 'item_id': n, 'management_no': q, 'serial_number': q, 'ip_address': q, 'status': q, 'location_id': n,
            'notes': q, 'is_active': n, 'source_excel_row': n, **AUDIT}
    insert(fp, 'inventory_units', cols, load('inventory_units.csv'), conv)

    cols = ['id', 'count_name', 'base_date', 'count_period', 'entry_date', 'start_date', 'end_date', 'status', 'notes',
            'created_by', 'created_at', 'confirmed_by', 'confirmed_at']
    conv = {'id': n, 'count_name': q, 'base_date': q, 'count_period': q, 'entry_date': q, 'start_date': q, 'end_date': q,
            'status': q, 'notes': q, 'created_by': q, 'created_at': lambda v: 'NOW()', 'confirmed_by': q,
            'confirmed_at': lambda v: 'NOW()'}
    insert(fp, 'inventory_counts', cols, load('inventory_counts.csv'), conv)

    cols = ['id', 'count_id', 'item_id', 'count_quantity', 'confirm_status', 'location_id_at_count', 'counted_by', 'counted_at',
            'notes', 'created_at', 'updated_at', 'created_by', 'updated_by', 'source_excel_rows']
    conv = {'id': n, 'count_id': n, 'item_id': n, 'count_quantity': n, 'confirm_status': q, 'location_id_at_count': n,
            'counted_by': q, 'counted_at': q, 'notes': q, 'source_excel_rows': q, **AUDIT}
    insert(fp, 'inventory_count_details', cols, load('inventory_count_details.csv'), conv)

    cols = ['id', 'count_id', 'unit_id', 'result', 'notes', 'created_at', 'updated_at', 'created_by', 'updated_by', 'source_excel_row']
    conv = {'id': n, 'count_id': n, 'unit_id': n, 'result': q, 'notes': q, 'source_excel_row': n, **AUDIT}
    insert(fp, 'inventory_count_unit_results', cols, load('inventory_count_unit_results.csv'), conv)

print('generated:', os.listdir(SQL_DIR))
