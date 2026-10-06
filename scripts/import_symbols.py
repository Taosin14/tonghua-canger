# -*- coding: utf-8 -*-
"""白族特色50样.xlsx -> backend/db/03_seed_symbols.sql

字段映射: 名称->name / 类别->category / 这是什么->description / 怎么来的->symbol_trace
按 name 幂等(ON DUPLICATE KEY UPDATE),重跑可增量更新。
"""
import json
import os

import openpyxl

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, '白族特色50样.xlsx')
OUT = os.path.join(ROOT, 'backend', 'db', '03_seed_symbols.sql')


def esc(s):
    return (s or '').replace('\\', '\\\\').replace("'", "\\'")


def main():
    wb = openpyxl.load_workbook(SRC, data_only=True)
    ws = wb['白族特色50样']

    lines = [
        '-- 文化符号种子数据(由 scripts/import_symbols.py 生成,勿手改;重跑可增量更新)',
        'USE canger;',
        'SET NAMES utf8mb4;',
    ]
    count = 0
    for row in ws.iter_rows(min_row=2, values_only=True):
        cells = [str(c or '').strip() if c is not None else '' for c in row[:5]]
        if len(cells) < 5:
            cells += [''] * (5 - len(cells))
        num, name, cat, desc, trace = cells
        if not name:
            continue
        v = {
            'name': esc(name),
            'cat': esc(cat or '其他'),
            'desc': esc(desc),
            'trace': esc(trace),
            'aliases': esc(json.dumps([], ensure_ascii=False)),
            'related': esc(json.dumps([], ensure_ascii=False)),
        }
        lines.append(
            'INSERT INTO symbols (name,category,description,symbol_trace,source,image_prompt,aliases,related_materials,status) VALUES '
            f"('{v['name']}','{v['cat']}','{v['desc']}','{v['trace']}','白族特色50样清单(内容组)','','{v['aliases']}','{v['related']}','published') "
            'ON DUPLICATE KEY UPDATE category=VALUES(category), description=VALUES(description), '
            'symbol_trace=VALUES(symbol_trace), source=VALUES(source);'
        )
        count += 1

    with open(OUT, 'w', encoding='utf-8') as f:
        f.write('\n'.join(lines) + '\n')
    print(f'OK: {count} 条符号 -> {OUT}')


if __name__ == '__main__':
    main()
