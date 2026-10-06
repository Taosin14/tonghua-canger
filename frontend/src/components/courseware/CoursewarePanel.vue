<script setup>
import { ref, computed } from 'vue'
import { api } from '../../api'
import { toastOk, toastErr } from '../../composables/useToast'
import { useExport } from '../../composables/useExport'
import { domainIcon, AGE_LABELS } from '../../utils/maps'

// 课件工坊:绘本 -> 结构化教学课件(封面/活动目标/导入/讲述/提问/操作/延伸)
// 与绘本形态明确区分:环节标签 + 目标区 + 指导语区 + 提问区
const stories = ref([])
const storyId = ref(null)
const slides = ref([])
const saving = ref(false)
const slidesEl = ref(null)
const { exportPng } = useExport()

const OPERATION_IDEAS = {
  扎染: '准备:白布、皮筋、蓝色颜料水。步骤:①把白布捏出小揪揪,用皮筋扎紧;②放进蓝色颜料水里泡一泡;③取出来晾干,打开看花纹!',
  三道茶: '准备:茶水三小杯(苦、甜、回味)。玩法:请小朋友闻一闻、尝一尝(温度合适时),说说三种味道像什么。',
  洱海: '准备:蓝色卡纸、白纸片。步骤:①把白纸片撕成海鸥的样子;②贴在蓝色卡纸上;③比比谁的"洱海"最漂亮。',
  火把节: '准备:红色、橙色纸条。步骤:①把纸条卷成小火把的形状;②贴到画纸上;③注意:真火把不能碰,安全第一!',
  三月街: '准备:小篮子、玩具物品若干。玩法:搭一个"小集市",小朋友轮流当"赶街人"和"摊主"。',
  瓦猫: '准备:黏土或橡皮泥。步骤:①捏一个圆圆的头;②捏出大大的嘴巴;③放到"屋顶"(纸板)上,守护我们的家!',
}
const DEFAULT_OPERATION = '准备:画纸和彩笔。步骤:①画一画故事里最喜欢的画面;②和同伴说说为什么喜欢它。'

async function loadStories() {
  try {
    const data = await api.stories({ status: 'published', page_size: 100 })
    stories.value = data.list
  } catch (e) {
    toastErr(e.message)
  }
}
loadStories()

const story = computed(() => stories.value.find((s) => s.id === Number(storyId.value)))

/** 生成结构化课件:封面->活动目标->导入->讲述页(含指导语+提问)->提问页->操作页->延伸页 */
async function generateSlides() {
  if (!story.value) {
    toastErr('请先选择一本绘本')
    return
  }
  try {
    const s = await api.story(story.value.id)
    const theme = s.theme || s.title
    const out = []

    // 1. 封面
    out.push({
      type: 'cover', title: `${s.title} · 主题教学活动`, subtitle: `适合 ${AGE_LABELS[s.age_band] || s.age_band} ｜ 主题:${theme}`,
      text: '', goal: '', guide: '', question: '',
    })
    // 2. 活动目标(五领域总览)
    const goals = (s.edu_goals_summary || []).map((g) => `${domainIcon(g.domain)} ${g.domain}领域:共 ${g.count} 处教育目标`).join('\n')
    out.push({
      type: 'goal', title: '🎯 活动目标', text: goals || '围绕故事内容设定各领域目标', goal: '', guide: '', question: '',
    })
    // 3. 导入
    out.push({
      type: 'intro', title: '🎬 情境导入', text: `今天老师带来了一个关于「${theme}」的故事。`, goal: '',
      guide: `教师引导语:出示与「${theme}」有关的图片或实物,请小朋友猜一猜今天的故事。`,
      question: '💬 提问:你见过「' + theme + '」吗?它是什么样子的?',
    })
    // 4. 讲述页(故事页 + 指导语 + 提问)
    for (const p of s.pages || []) {
      if (p.type === 'cover' || p.type === 'ending') continue
      const text = (p.text || '').replace(/\*\*/g, '').replace(/\n/g, ' ')
      out.push({
        type: 'story', title: `📖 讲述·第 ${p.page_no - 1} 页`, text,
        goal: p.goal_text ? `教学目标:${(p.goal_domains || []).map((d) => domainIcon(d) + d).join('')} ${p.goal_text}` : '',
        guide: '教师指导语:放慢语速朗读,用手指着画面中的细节,请小朋友观察。',
        question: '💬 提问:这一页里发生了什么?',
      })
    }
    // 5. 操作活动页
    out.push({
      type: 'activity', title: '✋ 操作活动', text: OPERATION_IDEAS[theme] || DEFAULT_OPERATION,
      goal: '', guide: '教师指导语:先示范一遍,再请小朋友动手;注意材料安全。', question: '',
    })
    // 6. 延伸页
    out.push({
      type: 'extend', title: '🏠 家园延伸', text: `回家和爸爸妈妈一起找一找身边的「${theme}」,把发现画下来,回班分享!`,
      goal: '', guide: '', question: '',
    })
    slides.value = out
    toastOk(`已生成 ${out.length} 页结构化课件,每页都可修改`)
  } catch (e) {
    toastErr(e.message)
  }
}

const SLIDE_TYPE_LABEL = { cover: '封面', goal: '目标', intro: '导入', story: '讲述', activity: '操作', extend: '延伸' }

function addSlide() {
  slides.value.push({ type: 'story', title: '📖 讲述·新页面', text: '', goal: '', guide: '', question: '' })
}

function removeSlide(i) {
  slides.value.splice(i, 1)
}

async function save() {
  if (!story.value || slides.value.length === 0) {
    toastErr('请先生成课件')
    return
  }
  saving.value = true
  try {
    await api.saveWork({
      work_type: 'courseware',
      title: story.value.title + ' · 教学课件',
      params: { story_id: story.value.id },
      story_id: story.value.id,
      status: 'done',
      content: { slides: slides.value },
    })
    toastOk('课件已保存到「我的作品」')
  } catch (e) {
    toastErr(e.message)
  } finally {
    saving.value = false
  }
}

async function exportSlides() {
  if (!slidesEl.value) return
  try {
    await exportPng(slidesEl.value, story.value ? story.value.title + '课件' : '课件')
    toastOk('导出成功')
  } catch (e) {
    toastErr('导出失败:' + e.message)
  }
}
</script>

<template>
  <div class="courseware-panel">
    <div class="cw-toolbar">
      <select v-model="storyId" class="cw-select">
        <option :value="null" disabled>选择一本绘本…</option>
        <option v-for="s in stories" :key="s.id" :value="s.id">
          《{{ s.title }}》({{ AGE_LABELS[s.age_band] || s.age_band }})
        </option>
      </select>
      <button class="btn" @click="generateSlides">🎓 自动生成课件</button>
      <button class="btn ghost" :disabled="!slides.length" @click="save">{{ saving ? '保存中…' : '💾 保存课件' }}</button>
      <button class="btn ghost" :disabled="!slides.length" @click="exportSlides">🖼️ 导出PNG</button>
    </div>

    <div v-if="!slides.length" class="empty-state">
      <div class="empty-icon">🎓</div>
      <div class="empty-title">课件工坊</div>
      <div class="empty-sub">选一本绘本 → 自动生成结构化教学课件:封面、活动目标、情境导入、讲述页(含教师指导语与课堂提问)、操作活动、家园延伸。每页都可修改。</div>
    </div>

    <div v-else ref="slidesEl" class="slides">
      <div v-for="(s, i) in slides" :key="i" class="slide" :class="'type-' + s.type">
        <div class="slide-bar">
          <span class="slide-badge">{{ SLIDE_TYPE_LABEL[s.type] || '页面' }} {{ i + 1 }}</span>
          <input v-model="s.title" class="slide-title" />
          <button class="slide-del" @click="removeSlide(i)">✕</button>
        </div>
        <div class="slide-body">
          <div v-if="s.subtitle" class="slide-subtitle">{{ s.subtitle }}</div>
          <div v-if="s.type === 'goal'" class="goal-lines">
            <div v-for="(g, gi) in s.text.split('\n')" :key="gi" class="goal-line">{{ g }}</div>
          </div>
          <div v-else-if="s.text" class="field">
            <textarea v-model="s.text" :rows="s.type === 'cover' ? 0 : 3" placeholder="正文内容"></textarea>
          </div>
          <div v-if="s.goal" class="slide-goal">
            <input v-model="s.goal" placeholder="教学目标(可改)" />
          </div>
          <div v-if="s.type !== 'cover' && s.type !== 'goal'" class="field">
            <label>教师指导语(可改)</label>
            <textarea v-model="s.guide" rows="2" placeholder="教师指导语…"></textarea>
          </div>
          <div v-if="s.type === 'intro' || s.type === 'story'" class="slide-question">
            <input v-model="s.question" placeholder="课堂提问(可改)" />
          </div>
        </div>
      </div>
      <div class="slide-add">
        <button class="btn ghost" @click="addSlide">＋ 手动添加一页</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.courseware-panel { padding-top: 4px; }
.cw-toolbar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; }
.cw-select {
  flex: 1;
  min-width: 200px;
  padding: 8px 10px;
  border: 1.5px solid #c9d3e3;
  border-radius: 10px;
  font-size: 14px;
  font-family: inherit;
}
.slides { display: flex; flex-direction: column; gap: 12px; }
/* 幻灯片形态:深色标题条 + 分区色块,与绘本明显区分 */
.slide {
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 4px 14px rgba(29, 53, 87, 0.12);
}
.slide-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  background: var(--indigo-deep);
  padding: 8px 12px;
}
.slide-badge {
  background: var(--accent);
  color: #fff;
  font-size: 11px;
  padding: 3px 10px;
  border-radius: 10px;
  white-space: nowrap;
  letter-spacing: 1px;
}
.slide-title {
  flex: 1;
  background: transparent;
  border: none;
  color: #fff;
  font-size: 15px;
  font-weight: 700;
  font-family: inherit;
}
.slide-title:focus { outline: none; }
.slide-del { border: none; background: rgba(255,255,255,0.15); color: #fff; border-radius: 8px; cursor: pointer; padding: 2px 8px; }
.slide-body { padding: 14px 16px; }
.slide-subtitle { font-size: 13px; color: var(--indigo-soft); margin-bottom: 8px; text-align: center; }
.goal-lines { display: flex; flex-direction: column; gap: 6px; }
.goal-line {
  background: #eef3fb;
  border-left: 4px solid var(--indigo);
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 13.5px;
  color: var(--indigo);
}
.slide-goal {
  background: #e8f0fb;
  border-radius: 8px;
  padding: 6px 10px;
  margin: 8px 0;
}
.slide-goal input { width: 100%; border: none; background: transparent; font-size: 12.5px; color: var(--indigo); font-family: inherit; }
.slide-goal input:focus { outline: none; }
.slide-question {
  background: #fdf6e3;
  border-radius: 8px;
  padding: 6px 10px;
  margin-top: 8px;
}
.slide-question input { width: 100%; border: none; background: transparent; font-size: 13px; color: #8a6a2f; font-weight: 600; font-family: inherit; }
.slide-question input:focus { outline: none; }
.field { margin-bottom: 6px; }
.field label { font-size: 12px; color: #9aa6b8; margin-bottom: 3px; display: block; }
.field textarea {
  width: 100%;
  border: 1.5px dashed #d5e2f2;
  border-radius: 8px;
  padding: 8px 10px;
  font-size: 14px;
  font-family: inherit;
  line-height: 1.7;
  resize: vertical;
}
.field textarea:focus { outline: none; border-color: var(--indigo-soft); }
.slide-add { text-align: center; margin-top: 4px; }
</style>
