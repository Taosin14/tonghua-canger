<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { marked } from 'marked'
import { useSpeech } from '../../composables/useSpeech'
import { useFlip } from '../../composables/useFlip'
import { domainIcon, AGE_LABELS, imgSrc } from '../../utils/maps'
import { api } from '../../api'

// 绘本阅读器:翻页(按钮/圆点/键盘)+ 中文朗读 + FLIP 过渡
// 交互逻辑整体移植自扎染绘本.html,数据源改为 pages JSON
const props = defineProps({
  story: { type: Object, required: true },
})

const pages = computed(() => props.story.pages || [])
const pageIndex = ref(0)
const page = computed(() => pages.value[pageIndex.value])
const total = computed(() => pages.value.length)

const bookEl = ref(null)
const { speak, stop, speaking } = useSpeech()
const { capture, play } = useFlip(bookEl)

// TTS 播放状态机:idle(空闲)/ synthesizing(合成中,按钮禁用)/ playing(播放中)
// 修复:点两次才播(合成中无反馈)、播一半重头(在途请求未作废)两个 bug
const audioPlaying = ref(false)
const ttsState = ref('idle')
let currentAudio = null
let ttsSeq = 0 // 每次 stopAll/翻页 +1,在途响应据此作废
const playing = computed(() => speaking.value || audioPlaying.value || ttsState.value === 'synthesizing')

function stopAll() {
  ttsSeq++
  stop()
  if (currentAudio) {
    currentAudio.pause()
    currentAudio = null
  }
  audioPlaying.value = false
  ttsState.value = 'idle'
}

function renderMarkdown(text) {
  // 重点词 **词** 高亮 + 换行
  return marked(text || '', { breaks: true })
}

function go(n) {
  if (n < 0 || n >= total.value || n === pageIndex.value) return
  capture()
  stopAll()
  pageIndex.value = n
  requestAnimationFrame(() => play())
}

/** 后台预取下一页音频(缓存热,翻页秒播) */
function prefetchNext() {
  const next = pages.value[pageIndex.value + 1]
  if (next && next.type !== 'cover' && next.text) {
    api.tts({ story_id: props.story.id, page_no: next.page_no, text: next.text }).catch(() => {})
  }
}

function readCurrent() {
  if (page.value?.type === 'cover') return
  const text = page.value?.text || ''
  if (audioPlaying.value) {
    stopAll() // 播放中点按钮 = 停止
    return
  }
  if (ttsState.value === 'synthesizing') return // 合成中去重,防连点
  const seq = ++ttsSeq
  ttsState.value = 'synthesizing'
  api.tts({ story_id: props.story.id, page_no: page.value.page_no, text })
    .then((res) => {
      if (seq !== ttsSeq) return // 已翻页/停止,作废
      ttsState.value = 'idle'
      if (!res.path) {
        speak(text)
        return
      }
      const a = new Audio('/' + res.path)
      currentAudio = a
      a.onended = () => {
        if (seq === ttsSeq) {
          ttsState.value = 'idle'
          audioPlaying.value = false
          prefetchNext()
        }
      }
      a.onerror = () => {
        if (seq !== ttsSeq) return
        ttsState.value = 'idle'
        audioPlaying.value = false
        speak(text) // 播放失败回退浏览器语音
      }
      audioPlaying.value = true
      ttsState.value = 'playing'
      a.play().catch(() => {
        if (seq !== ttsSeq) return
        ttsState.value = 'idle'
        audioPlaying.value = false
        speak(text)
      })
    })
    .catch(() => {
      if (seq !== ttsSeq) return
      ttsState.value = 'idle'
      speak(text)
    })
}

function onKey(e) {
  if (e.key === 'ArrowRight' || e.key === ' ') {
    e.preventDefault()
    go(pageIndex.value + 1)
  } else if (e.key === 'ArrowLeft') {
    e.preventDefault()
    go(pageIndex.value - 1)
  }
}

onMounted(() => document.addEventListener('keydown', onKey))
onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKey)
  stopAll()
})
watch(() => props.story.id, () => { pageIndex.value = 0; stopAll() })
</script>

<template>
  <div class="reader-stage">
    <div ref="bookEl" class="book">
      <!-- 封面 -->
      <div v-if="page.type === 'cover'" class="page cover">
        <span class="cover-badge">{{ AGE_LABELS[story.age_band] }} · {{ story.theme || '白族文化' }}</span>
        <h1 class="cover-h1">{{ story.title }}</h1>
        <div class="sub">{{ story.subtitle }}</div>
        <div class="cover-illus" v-if="story.cover_illus">
          <img :src="imgSrc(story.cover_illus)" :alt="story.title" @error="($event.target.style.display = 'none')" />
        </div>
        <div class="credit">童画苍洱 · AI 绘本工坊 ｜ 适合 {{ story.age_band }} 岁</div>
      </div>
      <!-- 内容/结尾页 -->
      <div v-else class="page">
        <div class="page-body">
          <div class="illus-wrap">
            <img
              v-if="page.illus"
              class="illus"
              :src="imgSrc(page.illus)"
              :alt="page.goal_text"
              @error="($event.target.style.display = 'none')"
            />
            <div class="illus-placeholder" :class="{ hidden: page.illus }">
              <span class="ph-icon">🎨</span>
              <span class="ph-text">插画提示词:<br>{{ page.illus_prompt || '待配图' }}</span>
            </div>
          </div>
          <div class="text-wrap">
            <span class="page-num">第 {{ page.page_no - 1 }} 页</span>
            <div class="story" v-html="renderMarkdown(page.text)"></div>
            <div v-if="page.goal_text" class="goal">
              <span v-for="d in page.goal_domains" :key="d">{{ domainIcon(d) }} {{ d }}</span>
              <span class="goal-text">教育目标:{{ page.goal_text }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 导航 -->
      <div class="nav">
        <button class="btn" :disabled="pageIndex === 0" @click="go(pageIndex - 1)">← 上一页</button>
        <div class="dots">
          <span
            v-for="(p, k) in pages"
            :key="k"
            class="dot"
            :class="{ on: k === pageIndex }"
            @click="go(k)"
          ></span>
        </div>
        <button class="btn sound" :class="{ playing }" :disabled="ttsState === 'synthesizing'" @click="readCurrent" title="朗读本页">
          {{ ttsState === 'synthesizing' ? '⏳ 合成中…' : playing ? '🔇 停止' : '🔊 听一听' }}
        </button>
        <span class="counter">{{ pageIndex + 1 }} / {{ total }}</span>
        <button class="btn" :disabled="pageIndex >= total - 1" @click="go(pageIndex + 1)">下一页 →</button>
      </div>
      <div class="hint">← → 键翻页 · 空格继续</div>
    </div>
  </div>
</template>

<style scoped>
.reader-stage {
  width: min(960px, 96vw);
  height: min(620px, 86vh);
  position: relative;
  margin: 0 auto;
}
.book {
  width: 100%;
  height: 100%;
  background: var(--paper);
  border-radius: 22px;
  box-shadow: 0 24px 60px rgba(29, 53, 87, 0.28), inset 0 0 0 6px #fff, inset 0 0 0 8px #e7d9bf;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  position: relative;
}
.page {
  flex: 1;
  display: flex;
  flex-direction: column;
  padding: 24px 30px 10px;
  animation: page-in 0.4s ease;
  min-height: 0;
}
@keyframes page-in {
  from { opacity: 0; transform: translateX(16px); }
  to { opacity: 1; transform: translateX(0); }
}
/* 封面 */
.page.cover {
  align-items: center;
  justify-content: center;
  text-align: center;
  background:
    radial-gradient(circle at 50% 30%, rgba(255, 255, 255, 0.7), transparent 60%),
    linear-gradient(160deg, #eaf1fb 0%, #d7e3f7 100%);
}
.cover-badge {
  display: inline-block;
  background: var(--accent);
  color: #fff;
  font-size: 13px;
  padding: 5px 14px;
  border-radius: 20px;
  letter-spacing: 2px;
  margin-bottom: 12px;
  box-shadow: 0 4px 10px rgba(232, 163, 61, 0.4);
}
.cover-h1 {
  font-size: clamp(32px, 6vw, 54px);
  color: var(--indigo-deep);
  letter-spacing: 4px;
  margin-bottom: 8px;
  text-shadow: 0 2px 0 #fff;
}
.sub { font-size: clamp(14px, 2.4vw, 19px); color: var(--indigo-soft); letter-spacing: 3px; }
.cover-illus { margin: 14px 0; }
.cover-illus img {
  width: min(280px, 56%);
  border-radius: 16px;
  box-shadow: 0 12px 26px rgba(29, 53, 87, 0.25);
}
.credit { margin-top: 10px; font-size: 12px; color: #9aa6b8; letter-spacing: 1px; }
/* 内容页 */
.page-body { flex: 1; display: flex; gap: 22px; align-items: center; min-height: 0; }
.illus-wrap {
  width: 46%;
  height: 100%;
  flex-shrink: 0;
  display: flex;
  align-items: center;
}
.illus {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 16px;
  box-shadow: 0 10px 24px rgba(29, 53, 87, 0.22);
  background: #eef3fb;
}
.illus-placeholder {
  width: 100%;
  height: 100%;
  border-radius: 16px;
  border: 2px dashed #c0cde0;
  background: #f0f5fc;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  text-align: center;
  padding: 10px;
}
.illus-placeholder.hidden { display: none; }
.ph-icon { font-size: 34px; }
.ph-text { font-size: 12px; color: #8a97ab; line-height: 1.7; }
.text-wrap { flex: 1; display: flex; flex-direction: column; justify-content: center; min-width: 0; }
.page-num {
  align-self: flex-start;
  background: var(--indigo);
  color: #fff;
  font-size: 13px;
  padding: 4px 12px;
  border-radius: 14px;
  margin-bottom: 12px;
  letter-spacing: 1px;
}
.story {
  font-size: clamp(17px, 2.6vw, 24px);
  line-height: 1.95;
  color: var(--ink);
  letter-spacing: 1px;
  font-weight: 500;
}
.story :deep(strong) { color: var(--indigo); font-weight: 700; }
.goal {
  margin-top: 14px;
  font-size: 13px;
  color: var(--indigo-soft);
  background: #eef3fb;
  padding: 9px 13px;
  border-radius: 12px;
  border-left: 4px solid var(--accent);
  line-height: 1.7;
}
.goal-text { margin-left: 4px; }
/* 导航 */
.nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 16px 6px;
  gap: 10px;
}
.dots { display: flex; gap: 6px; flex-wrap: wrap; justify-content: center; }
.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #c9d3e3;
  cursor: pointer;
  transition: 0.3s;
}
.dot.on { background: var(--indigo); transform: scale(1.25); }
.counter { font-size: 13px; color: #8a97ab; letter-spacing: 1px; min-width: 48px; text-align: center; }
.btn.sound.playing { border-color: var(--accent); color: var(--accent); }
.hint { position: absolute; bottom: 8px; right: 16px; font-size: 11px; color: #aab4c4; }
@media (max-width: 680px) {
  .page-body { flex-direction: column; gap: 10px; }
  .illus-wrap { width: 100%; height: 42%; }
  .page { padding: 16px 16px 8px; }
}
</style>
