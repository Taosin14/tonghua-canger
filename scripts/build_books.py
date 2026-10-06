# -*- coding: utf-8 -*-
"""6本白族民俗幼儿绘本.docx -> books/*.html + 合并导入 04_seed_stories.sql

用法: python scripts/build_books.py
产物:
  books/<slug>.html                 6 本可翻页绘本(复用《扎染绘本》样式, 图位预留 p1..pN)
  backend/db/04_seed_stories.sql    id 1《奶奶的蓝白花布》+ id 2~7 六本新书(按 title 幂等)
"""
import json
import os
import re

from docx import Document

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DOCX = os.path.join(ROOT, '6本白族民俗幼儿绘本.docx')
TPL = os.path.join(ROOT, '扎染绘本.html')
BOOKS_DIR = os.path.join(ROOT, 'books')
OUT = os.path.join(ROOT, 'backend', 'db', '04_seed_stories.sql')

import sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from extract_book import BookParser, esc, parse_goal  # 复用解析器与转义

# 六本新书元信息(按 docx 顺序)
BOOKS = [
    dict(slug='zharan_zb', theme='扎染', badge='小班 · 非遗工艺',
         sub='白族扎染小绘本', style='水彩'),
    dict(slug='sandaocha', theme='三道茶', badge='中班 · 饮食',
         sub='白族三道茶小绘本', style='水彩'),
    dict(slug='erhai', theme='洱海', badge='大班 · 自然',
         sub='洱海守护小绘本', style='水彩'),
    dict(slug='huobajie', theme='火把节', badge='小班 · 节日民俗',
         sub='火把节安全小绘本', style='水彩'),
    dict(slug='sanyuejie', theme='三月街', badge='中班 · 节日民俗',
         sub='三月街赶集小绘本', style='水彩'),
    dict(slug='wamao', theme='瓦猫', badge='大班 · 民间故事',
         sub='瓦猫守护小绘本', style='水彩'),
]

AGE_LABEL = {'3-4': '小班', '4-5': '中班', '5-6': '大班'}


def parse_docx():
    """解析 docx -> [{title, age_band, goal, characters, pages:[text...]}]"""
    d = Document(DOCX)
    stories, cur = [], None
    for p in d.paragraphs:
        t = p.text.strip()
        if not t:
            continue
        m = re.match(r'^绘本[一二三四五六七]：(.+?)（适合(\d)-(\d)岁）$', t)
        if m:
            if cur:
                stories.append(cur)
            cur = {'title': m.group(1), 'age_band': '%s-%s' % (m.group(2), m.group(3)),
                   'goal': '', 'characters': '', 'pages': []}
            continue
        if cur is None:
            continue
        if t.startswith('教育目标'):
            cur['goal'] = re.sub(r'^教育目标（(.*?)）：', r'\1——', t)
            continue
        if t.startswith('主角'):
            cur['characters'] = t
            continue
        if re.match(r'^第\d+页$', t):
            cur['pages'].append('')
            continue
        if cur['pages']:
            cur['pages'][-1] += t
    if cur:
        stories.append(cur)
    return stories


def load_prompts():
    """6个故事配文.xlsx -> {(书名, 页码): 配图需求}"""
    import openpyxl
    wb = openpyxl.load_workbook(os.path.join(ROOT, '6个故事配文.xlsx'))
    ws = wb['配图需求清单']
    out = {}
    for row in ws.iter_rows(min_row=2, values_only=True):
        _, book, page, _, prompt = [str(c or '').strip() if c is not None else '' for c in row[:5]]
        if not book or not page:
            continue
        m = re.match(r'^绘本[一二三四五六七]：(.*?)（', book)
        title = m.group(1) if m else book
        n = re.search(r'(\d+)', page)
        out[(title, int(n.group(1)) if n else 0)] = prompt
    return out


def tpl_parts():
    """拆分扎染绘本.html 为 head(样式)与 script(翻页逻辑)"""
    raw = open(TPL, encoding='utf-8').read()
    head = raw[raw.find('<!DOCTYPE'):raw.find('</head>') + len('</head>')]
    script = raw[raw.find('<script'):raw.find('</script>') + len('</script>')]
    return head, script


def build_html(meta, story, head, script):
    age_label = AGE_LABEL[story['age_band']]
    parts = [head, '<body>\n<div class="stage">\n  <div class="book" id="book">\n']
    # 封面
    parts.append('    <section class="page cover active" data-i="0">\n'
                 '      <span class="cover-badge">%s</span>\n'
                 '      <h1>%s</h1>\n'
                 '      <div class="sub">%s</div>\n'
                 '      <div class="credit">文 / 图 · 白族文化绘本资源库　|　适合 %s 岁</div>\n'
                 '    </section>\n'
                 % (meta['badge'], story['title'], meta['sub'],
                    story['age_band'].replace('-', '–')))
    for i, text in enumerate(story['pages'], start=1):
        goal = ('🌿 教育目标：' + story['goal']) if i == 1 else ''
        # 每句一行, 读起来有节奏
        lines = [s.strip() for s in re.split(r'[。！？]', text) if s.strip()]
        body = '<br>\n'.join(lines)
        parts.append('    <section class="page" data-i="%d">\n'
                     '      <div class="page-body">\n'
                     '        <div class="text-wrap">\n'
                     '          <span class="page-num">第 %d 页</span>\n'
                     '          <p class="story">%s</p>\n'
                     '          <div class="goal">%s</div>\n'
                     '        </div>\n'
                     '      </div>\n'
                     '    </section>\n'
                     % (i, i, body, goal))
    parts.append('  </div>\n</div>\n')
    parts.append(script)
    parts.append('</body>\n</html>\n')
    return ''.join(parts)


def import_one(html, story_id, theme, style, title_expect, prompts=None):
    prompts = prompts or {}
    """解析一本 HTML, 返回 SQL INSERT 片段"""
    p = BookParser()
    p.feed(open(html, encoding='utf-8').read())
    pages = p.pages
    assert pages, '未解析到页面: ' + html
    pages[-1]['type'] = 'ending'
    cover = pages[0]
    title = cover['title'] or title_expect
    subtitle = cover['subtitle'] or ''
    age_band = cover['badge']
    for label, band in (('小班', '3-4'), ('大班', '5-6')):
        if label in age_band:
            age_band = band
            break
    else:
        age_band = '4-5'
    summary = {}
    for pg in pages:
        for dm in pg['goal_domains']:
            if dm:
                summary[dm] = summary.get(dm, 0) + 1
    out_pages = []
    for pg in pages:
        out_pages.append({
            'page_no': pg['page_no'], 'type': pg['type'], 'text': pg['text'],
            'goal_text': pg['goal_text'], 'goal_domains': pg['goal_domains'],
            'illus': pg['illus'] or '',
            'illus_prompt': prompts.get((title, pg['page_no']), ''),
        })
    return (
        'INSERT INTO stories '
        '(id,title,subtitle,theme,material_id,age_band,page_count,style,cover_illus,pages,'
        ' edu_goals_summary,symbol_ids,source_type,ai_generated,status,published_at) VALUES '
        f"({story_id},'{esc(title)}','{esc(subtitle)}','{theme}',NULL,'{age_band}',{len(pages)},"
        f"'{style}','{esc(out_pages[0]['illus'])}','{esc(json.dumps(out_pages, ensure_ascii=False))}',"
        f"'{esc(json.dumps(summary, ensure_ascii=False))}','[]','seed',0,'published',NOW()) "
        'ON DUPLICATE KEY UPDATE title=VALUES(title), subtitle=VALUES(subtitle), '
        'age_band=VALUES(age_band), page_count=VALUES(page_count), pages=VALUES(pages), '
        'edu_goals_summary=VALUES(edu_goals_summary);'
    )


def main():
    os.makedirs(BOOKS_DIR, exist_ok=True)
    head, script = tpl_parts()
    stories = parse_docx()
    assert len(stories) == 6, 'docx 解析出 %d 本' % len(stories)
    lines = [
        '-- 种子故事(由 scripts/build_books.py 生成,勿手改;重跑可增量更新)',
        'USE canger;',
        'SET NAMES utf8mb4;',
    ]
    # id 1: 奶奶的蓝白花布(原绘本)
    lines.append(import_one(TPL, 1, '扎染', '水彩', '奶奶的蓝白花布'))
    # id 2~7: 六本新书(配图需求写入 illus_prompt)
    prompts = load_prompts()
    for i, (meta, story) in enumerate(zip(BOOKS, stories), start=2):
        html = os.path.join(BOOKS_DIR, meta['slug'] + '.html')
        with open(html, 'w', encoding='utf-8') as f:
            f.write(build_html(meta, story, head, script))
        lines.append(import_one(html, i, meta['theme'], meta['style'], story['title'], prompts))
        print('OK: 《%s》 %d 页 -> %s (id=%d)' % (story['title'], len(story['pages']), html, i))
    with open(OUT, 'w', encoding='utf-8') as f:
        f.write('\n'.join(lines) + '\n')
    print('OK: 7 本故事 -> %s' % OUT)


if __name__ == '__main__':
    main()
