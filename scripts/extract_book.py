# -*- coding: utf-8 -*-
"""扎染绘本.html -> backend/db/04_seed_stories.sql

提取 9 页《奶奶的蓝白花布》文本入库(种子故事,id 固定为 1,status=published)。
同时是"队友新故事 HTML -> 入库"的通用导入器。
用法: python scripts/extract_book.py [html路径]
"""
import json
import os
import re
import sys
from html.parser import HTMLParser

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = sys.argv[1] if len(sys.argv) > 1 else os.path.join(ROOT, '扎染绘本.html')
OUT = os.path.join(ROOT, 'backend', 'db', '04_seed_stories.sql')


def esc(s):
    return (s or '').replace('\\', '\\\\').replace("'", "\\'")


class BookParser(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.pages = []
        self.cur = None
        self.capture = None          # story / goal / h1 / sub / badge
        self.buf = []
        self.in_hl = False

    def _new_page(self, attrs, cover=False):
        self.cur = {
            'page_no': int(attrs.get('data-i', 0)) + 1,
            'type': 'cover' if cover else 'content',
            'text': '',
            'goal_text': '',
            'goal_domains': [],
            'illus': '',
            'title': '',
            'subtitle': '',
            'badge': '',
        }
        self.pages.append(self.cur)

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        cls = set(a.get('class', '').split())
        if tag == 'section' and 'page' in cls:
            self._new_page(a, cover=('cover' in cls))
            return
        if self.cur is None:
            return
        if tag == 'img':
            if 'illus' in cls or 'cover-img' in cls:
                self.cur['illus'] = a.get('src', '')
        elif tag == 'p' and 'story' in cls:
            self.capture = 'story'
            self.buf = []
        elif tag == 'div' and 'goal' in cls:
            self.capture = 'goal'
            self.buf = []
        elif tag == 'h1' and self.cur['type'] == 'cover':
            self.capture = 'h1'
            self.buf = []
        elif tag == 'div' and 'sub' in cls:
            self.capture = 'sub'
            self.buf = []
        elif tag == 'span' and 'cover-badge' in cls:
            self.capture = 'badge'
            self.buf = []
        elif tag == 'br' and self.capture == 'story':
            self.buf.append('\n')
        elif tag == 'b' and 'hl' in cls:
            self.in_hl = True
            self.buf.append('**')

    def handle_endtag(self, tag):
        if tag == 'b' and self.in_hl:
            self.in_hl = False
            self.buf.append('**')
        elif tag in ('p', 'div', 'h1', 'span'):
            if self.capture is None:
                return
            text = ''.join(self.buf).strip()
            if self.capture == 'story':
                self.cur['text'] = text
            elif self.capture == 'goal':
                self.cur['goal_text'], self.cur['goal_domains'] = parse_goal(text)
            elif self.capture == 'h1':
                self.cur['title'] = text
            elif self.capture == 'sub':
                self.cur['subtitle'] = text
            elif self.capture == 'badge':
                self.cur['badge'] = text
            self.capture = None
            self.buf = []

    def handle_data(self, data):
        if self.capture:
            self.buf.append(data)


EMOJI_RE = re.compile(r'[\U0001F000-\U0001FAFF☀-➿️✀-➿\s]+')


def parse_goal(text):
    """'🌿 教育目标:社会——感受祖孙相处的温暖' -> (goal_text, [domains])"""
    t = EMOJI_RE.sub('', text).strip()
    t = re.sub(r'^教育目标[:：]?', '', t).strip()
    m = re.match(r'^(.*?)(?:——|[:：]|—|-)(.*)$', t)
    if m:
        domains = [d.strip() for d in re.split(r'[/、]', m.group(1)) if d.strip()]
        return m.group(2).strip(), domains
    return t, []


def parse_age_from_badge(badge):
    if '小班' in badge:
        return '3-4'
    if '大班' in badge:
        return '5-6'
    return '4-5'


def main():
    with open(SRC, encoding='utf-8') as f:
        html = f.read()
    p = BookParser()
    p.feed(html)
    pages = p.pages
    if not pages:
        raise SystemExit('未解析到任何页面')

    # 最后一页标记为 ending
    for pg in pages:
        if pg['type'] == 'content':
            pg['type'] = 'content'
    pages[-1]['type'] = 'ending'

    # 插画路径重写为固定图库路径 p1..pN
    for pg in pages:
        if pg['illus']:
            pg['illus'] = f"images/books/zharan/p{pg['page_no']}.png"

    cover = pages[0]
    title = cover['title'] or '未命名绘本'
    subtitle = cover['subtitle'] or ''
    age_band = parse_age_from_badge(cover['badge'])

    # 五领域覆盖统计
    summary = {}
    for pg in pages:
        for d in pg['goal_domains']:
            if d:
                summary[d] = summary.get(d, 0) + 1
    goals_summary = [{'domain': k, 'count': v} for k, v in summary.items()]

    out_pages = []
    for pg in pages:
        out_pages.append({
            'page_no': pg['page_no'],
            'type': pg['type'],
            'text': pg['text'],
            'goal_text': pg['goal_text'],
            'goal_domains': pg['goal_domains'],
            'illus': pg['illus'],
            'illus_prompt': pg['illus_prompt'] if 'illus_prompt' in pg else '',
        })

    sql = (
        '-- 种子故事《奶奶的蓝白花布》(由 scripts/extract_book.py 生成,id 固定为 1)\n'
        'USE canger;\n'
        'SET NAMES utf8mb4;\n'
        'INSERT INTO stories '
        '(id,title,subtitle,theme,material_id,age_band,page_count,style,cover_illus,pages,'
        ' edu_goals_summary,symbol_ids,source_type,ai_generated,status,published_at) VALUES '
        f"(1,'{esc(title)}','{esc(subtitle)}','扎染',NULL,'{age_band}',{len(pages)},'水彩',"
        f"'{esc(pages[0]['illus'])}','{esc(json.dumps(out_pages, ensure_ascii=False))}',"
        f"'{esc(json.dumps(goals_summary, ensure_ascii=False))}','[]','seed',0,'published',NOW()) "
        'ON DUPLICATE KEY UPDATE title=VALUES(title), subtitle=VALUES(subtitle), '
        'age_band=VALUES(age_band), page_count=VALUES(page_count), cover_illus=VALUES(cover_illus), '
        'pages=VALUES(pages), edu_goals_summary=VALUES(edu_goals_summary);\n'
    )
    os.makedirs(os.path.dirname(OUT), exist_ok=True)
    with open(OUT, 'w', encoding='utf-8') as f:
        f.write(sql)
    print(f'OK: 《{title}》 {len(pages)} 页(年龄段 {age_band})-> {OUT}')


if __name__ == '__main__':
    main()
