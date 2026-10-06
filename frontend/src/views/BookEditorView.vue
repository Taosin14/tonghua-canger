<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api'
import { toastOk, toastErr } from '../composables/useToast'
import { useAuthStore } from '../stores/auth'
import { AGE_OPTIONS, STYLE_OPTIONS, imgSrc } from '../utils/maps'

// 全屏编辑器:改每页文本/教育目标/页序,AI 单页润色,保存/提交审核
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const story = ref(null)
const loading = ref(true)
const saving = ref(false)
const polishing = ref(-1) // 正在润色的页索引
const imaging = ref(-1) // 正在生图的页索引

const DOMAINS = ['健康', '语言', '社会', '科学', '艺术']

onMounted(load)

async function load() {
  try {
    story.value = await api.story(route.params.id)
  } catch (e) {
    toastErr('加载失败:' + e.message)
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    await api.updateStory(story.value.id, {
      title: story.value.title,
      subtitle: story.value.subtitle,
      theme: story.value.theme,
      age_band: story.value.age_band,
      style: story.value.style,
      pages: story.value.pages,
      characters: story.value.characters || [],
    })
    toastOk('已保存(版本 +1,状态回到草稿)')
    story.value.status = 'draft'
  } catch (e) {
    toastErr('保存失败:' + e.message)
  } finally {
    saving.value = false
  }
}

async function submitReview() {
  await save()
  try {
    await api.submitStory(story.value.id)
    toastOk('已提交审核')
    story.value.status = 'pending'
  } catch (e) {
    toastErr(e.message)
  }
}

// 角色设定卡(生图提示词注入:锁定黑发黑眼黄皮肤白族孩子,防止"小白"变小狗)
const extractingChars = ref(false)

async function extractCharacters() {
  extractingChars.value = true
  try {
    const res = await api.extractCharacters({ story_id: story.value.id })
    story.value.characters = res.characters || []
    toastOk('角色卡已提取,可修改后随保存入库')
  } catch (e) {
    toastErr('提取失败:' + e.message)
  } finally {
    extractingChars.value = false
  }
}

function addCharacter() {
  if (!story.value.characters) story.value.characters = []
  story.value.characters.push({ name: '', role: '', appearance: '黑色头发,黑色眼睛,黄色皮肤,穿白族传统服饰,是人类' })
}

/** 把当前页插图设为指定角色的定妆照(IP-Adapter 参考图,锁定跨页长相) */
const charTarget = ref('')

async function setCharImage(i) {
  const page = story.value.pages[i]
  if (!page.illus) {
    toastErr('该页还没有插图,先生成一张')
    return
  }
  if (!charTarget.value) {
    toastErr('请先选择角色(上方角色卡里要有该角色)')
    return
  }
  try {
    const res = await api.setCharacterImage({ story_id: story.value.id, name: charTarget.value, page_no: page.page_no })
    const chars = story.value.characters || []
    const c = chars.find((x) => x.name === charTarget.value)
    if (c) c.ref_image = res.ref_image
    else chars.push({ name: charTarget.value, role: '', appearance: '', ref_image: res.ref_image })
    story.value.characters = chars
    toastOk(`已设为「${charTarget.value}」的定妆照,后续生图将锁定这个长相`)
  } catch (e) {
    toastErr(e.message)
  }
}

function removeCharacter(i) {
  story.value.characters.splice(i, 1)
}

async function polishPage(i) {
  const page = story.value.pages[i]
  if (!page.text) { toastErr('该页没有文本'); return }
  polishing.value = i
  try {
    const res = await api.polish({ story_id: story.value.id, mode: 'polish', text: page.text })
    if (res.ai_used) {
      page.text = res.result.text || page.text
      toastOk('AI 润色完成')
    } else {
      toastErr(res.message || 'AI 不可用')
    }
  } catch (e) {
    toastErr('润色失败:' + e.message)
  } finally {
    polishing.value = -1
  }
}

async function aiGoal(i) {
  const page = story.value.pages[i]
  if (!page.text) { toastErr('该页没有文本'); return }
  polishing.value = i
  try {
    const res = await api.polish({ story_id: story.value.id, mode: 'goal', text: page.text })
    if (res.ai_used) {
      page.goal_text = res.result.goal_text || page.goal_text
      page.goal_domains = res.result.goal_domains || page.goal_domains
      toastOk('AI 已配教育目标')
    } else {
      toastErr(res.message || 'AI 不可用')
    }
  } catch (e) {
    toastErr(e.message)
  } finally {
    polishing.value = -1
  }
}

function movePage(i, dir) {
  const j = i + dir
  if (j < 0 || j >= story.value.pages.length) return
  const pages = story.value.pages
  ;[pages[i], pages[j]] = [pages[j], pages[i]]
  pages.forEach((p, k) => { p.page_no = k + 1 })
}

function toggleDomain(page, d) {
  const i = page.goal_domains.indexOf(d)
  if (i >= 0) page.goal_domains.splice(i, 1)
  else page.goal_domains.push(d)
}

/** 智谱 CogView 生成本页插画(提示词 = 配图需求) */
async function aiImage(i) {
  const page = story.value.pages[i]
  if (!page.illus_prompt) {
    toastErr('请先填写该页的插画提示词(配图需求)')
    return
  }
  imaging.value = i
  try {
    const res = await api.generateImage({ story_id: story.value.id, page_no: page.page_no })
    if (res.generated) {
      page.illus = res.illus
      toastOk('插画已生成并保存(队友可随时换图)')
    } else {
      toastErr(res.message || '生图失败')
    }
  } catch (e) {
    toastErr('生图失败:' + e.message)
  } finally {
    imaging.value = -1
  }
}
</script>

<template>
  <div class="editor-view">
    <div class="editor-toolbar card">
      <button class="btn ghost sm" @click="router.back()">← 返回</button>
      <span class="toolbar-title">✏️ 编辑器</span>
      <span class="toolbar-spacer"></span>
      <button class="btn sm" :disabled="saving" @click="save">💾 保存</button>
      <button v-if="story && (story.status === 'draft' || story.status === 'rejected')" class="btn ok sm" @click="submitReview">📤 保存并提交审核</button>
    </div>

    <div v-if="loading" class="card loading-card">⏳ 加载中…</div>

    <template v-else-if="story">
      <div class="meta-card card">
        <div class="field-row">
          <div class="field">
            <label>书名</label>
            <input v-model="story.title" />
          </div>
          <div class="field">
            <label>副标题</label>
            <input v-model="story.subtitle" />
          </div>
          <div class="field">
            <label>主题</label>
            <input v-model="story.theme" />
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label>年龄段</label>
            <select v-model="story.age_band">
              <option v-for="a in AGE_OPTIONS" :key="a.value" :value="a.value">{{ a.label }}</option>
            </select>
          </div>
          <div class="field">
            <label>风格</label>
            <select v-model="story.style">
              <option v-for="s in STYLE_OPTIONS" :key="s" :value="s">{{ s }}</option>
            </select>
          </div>
          <div class="field">
            <label>页数</label>
            <input :value="story.pages.length" disabled />
          </div>
        </div>
        <div class="char-block">
          <div class="char-head">
            <span class="char-title">👶 角色设定卡(生图时锁定人物长相,防止"小白"被画成小狗)</span>
            <span class="char-actions">
              <button class="btn ghost sm" :disabled="extractingChars" @click="extractCharacters">
                {{ extractingChars ? '✨ 提取中…' : '✨ AI 提取角色卡' }}
              </button>
              <button class="btn ghost sm" @click="addCharacter">＋ 加角色</button>
            </span>
          </div>
          <div v-for="(c, i) in story.characters || []" :key="i" class="char-row">
            <img v-if="c.ref_image" class="char-ref" :src="imgSrc(c.ref_image)" :title="c.name + ' 定妆照'" />
            <input v-model="c.name" placeholder="名字(如:小白)" />
            <input v-model="c.role" placeholder="角色(如:主角)" />
            <input v-model="c.appearance" placeholder="外貌(黑头发黑眼睛黄皮肤白族服饰)" />
            <button class="btn ghost sm" @click="removeCharacter(i)">✕</button>
          </div>
          <p v-if="!story.characters?.length" class="char-tip">点"AI 提取角色卡"自动从故事文本识别,或手动添加</p>
        </div>
      </div>

      <div v-for="(page, i) in story.pages" :key="page.page_no" class="page-card card">
        <div class="page-head">
          <span class="badge" :class="'type-' + page.type">第 {{ page.page_no }} 页 · {{ { cover: '封面', content: '内容', ending: '结尾互动' }[page.type] }}</span>
          <span class="page-actions">
            <button class="btn ghost sm" :disabled="i === 0" @click="movePage(i, -1)">↑</button>
            <button class="btn ghost sm" :disabled="i === story.pages.length - 1" @click="movePage(i, 1)">↓</button>
          </span>
        </div>
        <template v-if="page.type !== 'cover'">
          <div class="field">
            <label>页文本(重点词用 **词** 标记)</label>
            <textarea v-model="page.text" rows="4"></textarea>
          </div>
          <div class="field">
            <label>教育目标</label>
            <div class="goal-row">
              <input v-model="page.goal_text" placeholder="如:感受祖孙相处的温暖" />
              <button class="btn ghost sm" :disabled="polishing === i" @click="aiGoal(i)">
                {{ polishing === i ? '✨ 生成中…' : '✨ AI 配目标' }}
              </button>
            </div>
            <div class="domain-chips">
              <span
                v-for="d in DOMAINS"
                :key="d"
                class="domain-chip"
                :class="{ on: page.goal_domains.includes(d) }"
                @click="toggleDomain(page, d)"
              >{{ d }}</span>
            </div>
          </div>
          <div class="field">
            <label>配图需求(也是 AI 生图提示词;有照片可直接替换图片文件)</label>
            <input v-model="page.illus_prompt" placeholder="如:阳光洒满的美工室里,老师拿出软软的白布…" />
          </div>
          <div class="field">
            <label>配图重点(事物为主的页选"事物",只画特写不画人)</label>
            <select v-model="page.focus_manual" class="focus-select">
              <option value="auto">🎯 自动判断</option>
              <option value="person">👤 人物为主</option>
              <option value="object">🧺 事物为主</option>
            </select>
          </div>
          <div class="illus-row">
            <img v-if="page.illus" class="illus-thumb" :src="imgSrc(page.illus)" :alt="page.goal_text" />
            <span v-else class="illus-none">暂无配图</span>
            <span v-if="page.illus" class="char-set">
              <select v-model="charTarget" class="char-select">
                <option value="" disabled>选角色设为定妆照</option>
                <option v-for="c in story.characters || []" :key="c.name" :value="c.name">{{ c.name }}</option>
              </select>
              <button class="btn ghost sm" @click="setCharImage(i)">👤 设为定妆照</button>
            </span>
          </div>
          <div class="page-foot">
            <button class="btn ghost sm" :disabled="polishing === i" @click="polishPage(i)">
              {{ polishing === i ? '✨ 润色中…' : '✨ AI 润色本页' }}
            </button>
            <button class="btn sm" :disabled="imaging === i" @click="aiImage(i)">
              {{ imaging === i ? '🎨 生成中(约30秒)…' : '🎨 AI 生成本页插画' }}
            </button>
          </div>
        </template>
        <div v-else class="cover-tip">封面页:书名 / 副标题 / 封面图在上方"基本信息"中设置</div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.editor-view { max-width: 880px; margin: 0 auto; }
.editor-toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.toolbar-title { font-size: 15px; font-weight: 700; color: var(--indigo-deep); }
.toolbar-spacer { flex: 1; }
.loading-card { text-align: center; padding: 60px; color: var(--indigo-soft); }
.meta-card { margin-bottom: 14px; }
.char-block { margin-top: 6px; border-top: 1px dashed #d5e2f2; padding-top: 10px; }
.char-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; }
.char-title { font-size: 13px; color: var(--indigo-soft); }
.char-actions { display: flex; gap: 6px; }
.char-row { display: flex; gap: 6px; margin-bottom: 6px; }
.char-row input {
  flex: 1;
  padding: 7px 10px;
  border: 1.5px solid #c9d3e3;
  border-radius: 8px;
  font-size: 13px;
  font-family: inherit;
}
.char-row input:first-child { flex: 0 0 110px; }
.char-row input:nth-child(2) { flex: 0 0 90px; }
.char-tip { font-size: 12px; color: #9aa6b8; }
.field-row { display: flex; gap: 12px; }
.field-row .field { flex: 1; }
.page-card { margin-bottom: 14px; }
.page-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.type-cover { background: #f3ead8; color: #8a6a2f; }
.type-ending { background: #e3f0e8; color: #2b8a5c; }
.page-actions { display: flex; gap: 4px; }
.goal-row { display: flex; gap: 8px; align-items: center; }
.domain-chips { display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap; }
.domain-chip {
  font-size: 12px;
  padding: 3px 10px;
  border-radius: 12px;
  background: #eef3fb;
  color: var(--indigo-soft);
  cursor: pointer;
  border: 1px solid transparent;
}
.domain-chip.on { background: var(--indigo); color: #fff; }
.page-foot { display: flex; justify-content: flex-end; gap: 8px; }
.illus-row { margin: 8px 0 10px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.char-set { display: flex; align-items: center; gap: 6px; }
.char-select {
  padding: 5px 8px;
  border: 1.5px solid #c9d3e3;
  border-radius: 8px;
  font-size: 12px;
  font-family: inherit;
}
.char-ref {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  object-fit: cover;
  flex-shrink: 0;
  box-shadow: 0 2px 6px rgba(29, 53, 87, 0.2);
}
.focus-select {
  max-width: 220px;
}
.illus-thumb {
  max-width: 220px;
  border-radius: 10px;
  box-shadow: 0 6px 16px rgba(29, 53, 87, 0.2);
}
.illus-none { font-size: 12px; color: #9aa6b8; }
.cover-tip { font-size: 13px; color: #9aa6b8; }
</style>
