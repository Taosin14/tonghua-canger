# -*- coding: utf-8 -*-
"""books/*.html -> backend/db/06_seed_books.sql

队友交付的 6 本故事批量入库:
- 复用 extract_book.BookParser 解析(封面/内容页/结尾/教育目标)
- 配图需求从 6个故事配文.xlsx 按"页面原文"模糊匹配,填入每页 illus_prompt
- 入库为 source_type='user', status='published'(内容组人工创作,直接进图书馆)
"""
import json
import os
import re
import sys

import openpyxl

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from extract_book import BookParser, parse_goal, esc

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BOOKS_DIR = os.path.join(ROOT, 'books')
OUT = os.path.join(ROOT, 'backend', 'db', '06_seed_books.sql')

THEME_MAP = {
    'erhai': '洱海',
    'huobajie': '火把节',
    'sandaocha': '三道茶',
    'sanyuejie': '三月街',
    'wamao': '瓦猫',
    'zharan_zb': '扎染',
}


def norm(s):
    return re.sub(r'\s+', '', s or '')


def load_pic_requests():
    """6个故事配文.xlsx -> {短书名: [(原文片段, 需求)]}"""
    wb = openpyxl.load_workbook(os.path.join(ROOT, '6个故事配文.xlsx'), data_only=True)
    ws = wb['配图需求清单']
    reqs = {}
    for row in ws.iter_rows(min_row=2, values_only=True):
        cells = [str(c or '').strip() if c is not None else '' for c in row[:5]]
        if len(cells) < 5:
            continue
        num, book, page, text, req = cells  # 列:序号/绘本名称/页码/页面原文/配图需求
        if not book:
            continue
        short = re.sub(r'^绘本[一二三四五六]+[:：]', '', book)
        short = re.sub(r'[（(]适合.*?[）)]', '', short).strip()  # 全角/半角括号都要处理
        key = norm(short)
        reqs.setdefault(key, []).append((norm(text), req))
    return reqs


def strip_punct(s):
    """去所有标点后比较(HTML 里 <br> 换行对应清单里的句号,直接比对会错位)"""
    return re.sub(r'[。，！？、,.!?;；:：\s]', '', s)


def match_request(reqs, page_text):
    """按原文前 14 字(去标点)匹配配图需求,匹配不到返回空串"""
    t = strip_punct(page_text)[:14]
    if not t:
        return ''
    for raw, req in reqs:
        if t == strip_punct(raw)[:14]:
            return req
    return ''


def summary_of(pages):
    count = {}
    for pg in pages:
        for d in pg['goal_domains']:
            if d:
                count[d] = count.get(d, 0) + 1
    return [{'domain': k, 'count': v} for k, v in count.items()]


def parse_age(badge):
    if '小班' in badge:
        return '3-4'
    if '大班' in badge:
        return '5-6'
    return '4-5'


def main():
    reqs = load_pic_requests()
    lines = [
        '-- 内容组 6 本故事(由 scripts/import_books.py 生成,勿手改;重跑覆盖式导入)',
        'USE canger;',
        'SET NAMES utf8mb4;',
    ]
    ids = {}  # 文件名 -> 入库 id(第 2 个开始,种子故事 id=1)
    next_id = 2
    for fname in sorted(os.listdir(BOOKS_DIR)):
        if not fname.endswith('.html'):
            continue
        stem = fname[:-5]
        if stem not in THEME_MAP:
            print(f'[warn] 未映射主题的文件 {fname},跳过')
            continue
        with open(os.path.join(BOOKS_DIR, fname), encoding='utf-8') as f:
            p = BookParser()
            p.feed(f.read())
        pages = p.pages
        if not pages:
            print(f'[warn] {fname} 未解析到页面,跳过')
            continue
        pages[-1]['type'] = 'ending'
        cover = pages[0]
        title = cover['title'] or stem
        subtitle = cover['subtitle'] or (THEME_MAP[stem] + '小绘本')
        age = parse_age(cover['badge'])

        book_key = norm(title)
        for pg in pages:
            if pg['type'] == 'content':
                pg['illus_prompt'] = match_request(reqs.get(book_key, []), pg['text'])
            else:
                pg['illus_prompt'] = ''
            pg['illus'] = ''  # 新书图片待队友 C 交付,先留空显示占位卡

        out_pages = [{
            'page_no': pg['page_no'], 'type': pg['type'], 'text': pg['text'],
            'goal_text': pg['goal_text'], 'goal_domains': pg['goal_domains'],
            'illus': pg['illus'], 'illus_prompt': pg['illus_prompt'],
        } for pg in pages]

        lines.append(
            'INSERT INTO stories '
            '(id,title,subtitle,theme,material_id,age_band,page_count,style,cover_illus,pages,'
            ' edu_goals_summary,symbol_ids,source_type,ai_generated,status,published_at) VALUES '
            f"({next_id},'{esc(title)}','{esc(subtitle)}','{THEME_MAP[stem]}',NULL,'{age}',{len(pages)},'水彩','',"
            f"'{esc(json.dumps(out_pages, ensure_ascii=False))}',"
            f"'{esc(json.dumps(summary_of(pages), ensure_ascii=False))}','[]','user',0,'published',NOW()) "
            'ON DUPLICATE KEY UPDATE title=VALUES(title), subtitle=VALUES(subtitle), age_band=VALUES(age_band), '
            'page_count=VALUES(page_count), pages=VALUES(pages), edu_goals_summary=VALUES(edu_goals_summary);'
        )
        ids[stem] = next_id
        next_id += 1
        print(f'OK: 《{title}》 {len(pages)} 页 ({age} 岁) 配图需求命中 {sum(1 for p in pages if p["illus_prompt"])} 页')

    with open(OUT, 'w', encoding='utf-8') as f:
        f.write('\n'.join(lines) + '\n')
    print(f'共 {len(ids)} 本 -> {OUT}')


if __name__ == '__main__':
    main()
