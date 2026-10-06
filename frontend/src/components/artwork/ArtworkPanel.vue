<script setup>
import { ref, computed } from 'vue'
import { api } from '../../api'
import { toastOk, toastErr } from '../../composables/useToast'
import { useExport } from '../../composables/useExport'
import { imgSrc } from '../../utils/maps'

// 环创素材:文化符号纹样卡 + 教师手动上传图文卡 -> 网格排版 -> 保存/导出(打印贴墙)
const symbols = ref([])
const symbolQ = ref('')
const cards = ref([])
const saving = ref(false)
const gridEl = ref(null)
const { exportPng } = useExport()

const customTitle = ref('')
const customText = ref('')
const uploading = ref(false)

async function searchSymbols() {
  try {
    const data = await api.symbols({ q: symbolQ.value || undefined, page_size: 30 })
    symbols.value = data.list
  } catch (e) {
    toastErr(e.message)
  }
}
searchSymbols()

const addedNames = computed(() => new Set(cards.value.filter((c) => c.kind === 'symbol').map((c) => c.name)))

function addSymbol(s) {
  if (addedNames.value.has(s.name)) {
    toastErr('这个符号已经加过了')
    return
  }
  cards.value.push({ kind: 'symbol', name: s.name, trace: s.symbol_trace || s.description || '', category: s.category })
  toastOk(`已加入「${s.name}」纹样卡`)
}

async function uploadImage(ev) {
  const file = ev.target.files?.[0]
  if (!file) return
  if (file.size > 8 * 1024 * 1024) {
    toastErr('图片需小于 8MB')
    return
  }
  uploading.value = true
  try {
    const b64 = await new Promise((resolve, reject) => {
      const r = new FileReader()
      r.onload = () => resolve(String(r.result).split(',')[1])
      r.onerror = reject
      r.readAsDataURL(file)
    })
    const res = await api.upload({ image: b64, type: 'artwork' })
    cards.value.push({ kind: 'custom', title: customTitle.value || '文化墙', text: customText.value, image: res.path })
    customTitle.value = ''
    customText.value = ''
    toastOk('图片已上传并加入环创卡')
  } catch (e) {
    toastErr('上传失败:' + e.message)
  } finally {
    uploading.value = false
    ev.target.value = ''
  }
}

function removeCard(i) {
  cards.value.splice(i, 1)
}

async function save() {
  if (!cards.value.length) {
    toastErr('先加入一些卡片')
    return
  }
  saving.value = true
  try {
    await api.saveWork({
      work_type: 'artwork',
      title: '白族文化环创素材包',
      params: {},
      status: 'done',
      content: { cards: cards.value },
    })
    toastOk('环创素材包已保存到「我的作品」')
  } catch (e) {
    toastErr(e.message)
  } finally {
    saving.value = false
  }
}

async function exportGrid() {
  if (!gridEl.value) return
  try {
    await exportPng(gridEl.value, '白族文化环创素材')
    toastOk('导出成功,可打印布置教室')
  } catch (e) {
    toastErr('导出失败:' + e.message)
  }
}
</script>

<template>
  <div class="artwork-panel">
    <div class="aw-toolbar">
      <input v-model="symbolQ" class="aw-search" placeholder="搜索文化符号(如:扎染/瓦猫)" @keyup.enter="searchSymbols" />
      <button class="btn sm" @click="searchSymbols">搜索</button>
      <span class="aw-tip">点符号加入纹样卡;或右侧手动上传图片+文案</span>
    </div>

    <div class="aw-symbols">
      <button v-for="s in symbols" :key="s.id" class="sym-chip" :disabled="addedNames.has(s.name)" @click="addSymbol(s)">
        {{ s.name }}
      </button>
      <p v-if="!symbols.length" class="aw-empty">没有找到符号,试试其他关键词</p>
    </div>

    <div class="aw-custom card">
      <div class="field-row">
        <div class="field">
          <label>卡片标题(手动加)</label>
          <input v-model="customTitle" placeholder="如:三月街" />
        </div>
        <div class="field">
          <label>卡片文案</label>
          <input v-model="customText" placeholder="如:农历三月十五至廿一" />
        </div>
        <div class="field upload-field">
          <label>{{ uploading ? '上传中…' : '配图(可选)' }}</label>
          <input type="file" accept="image/*" @change="uploadImage" />
        </div>
      </div>
    </div>

    <div class="aw-actions">
      <button class="btn" :disabled="!cards.length" @click="save">{{ saving ? '保存中…' : '💾 保存素材包' }}</button>
      <button class="btn ghost" :disabled="!cards.length" @click="exportGrid">🖼️ 导出PNG(打印用)</button>
    </div>

    <div v-if="cards.length" ref="gridEl" class="aw-grid">
      <div v-for="(c, i) in cards" :key="i" class="aw-card" :class="'kind-' + c.kind">
        <button class="aw-remove" @click="removeCard(i)">✕</button>
        <template v-if="c.kind === 'symbol'">
          <div class="aw-pattern">〰️🌀</div>
          <div class="aw-name">{{ c.name }}</div>
          <div class="aw-trace">{{ c.trace }}</div>
          <div class="aw-tag">📍 文化溯源</div>
        </template>
        <template v-else>
          <img v-if="c.image" class="aw-img" :src="imgSrc(c.image)" :alt="c.title" />
          <div class="aw-name">{{ c.title }}</div>
          <div class="aw-trace">{{ c.text }}</div>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.artwork-panel { padding-top: 4px; }
.aw-toolbar { display: flex; gap: 8px; align-items: center; margin-bottom: 12px; flex-wrap: wrap; }
.aw-search {
  width: 240px;
  padding: 8px 10px;
  border: 1.5px solid #c9d3e3;
  border-radius: 10px;
  font-size: 13px;
  font-family: inherit;
}
.aw-tip { font-size: 12px; color: #9aa6b8; }
.aw-symbols { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
.sym-chip {
  border: 1.5px solid var(--indigo-soft);
  background: #eef3fb;
  color: var(--indigo);
  padding: 5px 12px;
  border-radius: 16px;
  font-size: 13px;
  cursor: pointer;
  font-family: inherit;
}
.sym-chip:hover { background: var(--indigo); color: #fff; }
.sym-chip:disabled { opacity: 0.4; cursor: not-allowed; }
.aw-empty { font-size: 13px; color: #9aa6b8; }
.aw-custom { margin-bottom: 12px; }
.field-row { display: flex; gap: 10px; }
.field-row .field { flex: 1; }
.upload-field input[type="file"] { padding: 6px; font-size: 12px; }
.aw-actions { display: flex; gap: 8px; margin-bottom: 14px; }
.aw-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px;
  background: #fff;
  padding: 16px;
  border-radius: 14px;
}
.aw-card {
  position: relative;
  border: 2px dashed #c9d3e3;
  border-radius: 12px;
  padding: 14px;
  min-height: 160px;
  background: #fbfaf6;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
}
.kind-custom { border-style: solid; border-color: var(--accent); }
.aw-remove {
  position: absolute;
  top: 6px;
  right: 6px;
  border: none;
  background: #f0f0f0;
  border-radius: 10px;
  cursor: pointer;
  font-size: 12px;
  padding: 2px 7px;
}
.aw-pattern { font-size: 22px; letter-spacing: 4px; color: var(--indigo); margin-bottom: 6px; }
.aw-name { font-size: 16px; font-weight: 700; color: var(--indigo-deep); margin-bottom: 6px; }
.aw-trace { font-size: 12px; color: var(--indigo-soft); line-height: 1.6; }
.aw-tag { font-size: 11px; color: var(--accent); margin-top: 8px; }
.aw-img { max-width: 100%; max-height: 110px; border-radius: 8px; margin-bottom: 6px; object-fit: cover; }
</style>
