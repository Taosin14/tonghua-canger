# -*- coding: utf-8 -*-
"""校验种子 SQL:INSERT 行数统计 + JSON 列合法性

用法: python scripts/seed_check.py
"""
import json
import os
import re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_DIR = os.path.join(ROOT, 'backend', 'db')

EXPECT = {
    '02_seed_materials.sql': ('materials', 60),
    '03_seed_symbols.sql': ('symbols', 50),
    '04_seed_stories.sql': ('stories', 1),
    '05_seed_admin.sql': ('users', 1),  # 1 条多行 INSERT(admin+teacher 两个账号)
    '06_seed_books.sql': ('stories', 6),
}


def main():
    ok_all = True
    for fname, (table, expect) in EXPECT.items():
        path = os.path.join(DB_DIR, fname)
        if not os.path.exists(path):
            print(f'[skip] {fname} 不存在(预计 {expect} 条 {table},待内容组交付)')
            continue
        text = open(path, encoding='utf-8').read()
        # INSERT 语句条数
        n = len(re.findall(r'INSERT INTO\s+\w+', text, re.I))
        # 提取每个 JSON 列值(在单引号内的 JSON)做 json.loads 校验
        bad = 0
        for m in re.finditer(r"'(\[|\{)(?:[^'\\]|\\.)*'", text):
            raw = m.group(0)[1:-1]
            try:
                json.loads(raw.encode().decode('unicode_escape'))
            except Exception:
                bad += 1
        status = 'OK' if n >= expect else 'WARN'
        if n < expect or bad:
            ok_all = False
        print(f'[{status}] {fname}: {n} 条 INSERT(预期≥{expect}),JSON 异常 {bad} 处')
    print('全部通过' if ok_all else '存在缺口(见上)')


if __name__ == '__main__':
    main()
