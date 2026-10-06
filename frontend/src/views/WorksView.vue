<script setup>
import { ref, onMounted } from 'vue'
import { api } from '../api'
import { toastErr, toastOk } from '../composables/useToast'
import { useExport } from '../composables/useExport'
import { imgSrc } from '../utils/maps'

// 我的作品:绘本/课件/环创 记录;课件与环创点"查看"直接预览自己的内容
const works = ref([])
const loading = ref(true)
const viewWork = ref(null) // 正在预览的课件/环创作品
const previewEl = ref(null)
const { exportPng } = useExport()

onMounted(load)

async function load() {
  loading.value = true
  try {
    const data = await api.works({ page_size: 100 })
    works.value = data.list
  } catch (e) {
    toastErr(e.message)
  } finally {
    loading.value = false
  }
}

const TYPE_LABELS = { book: '📖 绘本', courseware: '🎓 课件', artwork: '🎨 环创' }

function openWork(w) {
  if (w.work_type === 'book' && w.story_id) {
    window.open(`/stories/${w.story_id}`)
    return
  }
  viewWork.value = w
}

async function exportPreview() {
  if (!previewEl.value) return
  try {
    await exportPng(previewEl.value, viewWork.value.title || '作品')
    toastOk('导出成功')
  } catch (e) {
    toastErr('导出失败:' + e.message)
  }
}

async function remove(id) {
  try {
    await api.deleteWork(id)
    toastOk('已删除')
    load()
  } catch (e) {
    toastErr(e.message)
  }
}
</script>

<template>
  <div class="works-view">
    <h2 class="view-title">📚 我的作品</h2>
    <div v-if="loading" class="card loading-card">⏳ 加载中…</div>
    <div v-else-if="works.length === 0" class="card empty-state">
      <div class="empty-icon">📚</div>
      <div class="empty-title">还没有作品</div>
      <div class="empty-sub">生成绘本后会自动记录在这里;导出过的格式也会记录</div>
    </div>
    <div v-else class="work-list">
      <div v-for="w in works" :key="w.id" class="work-item card">
        <div class="w-info">
          <span class="badge">{{ TYPE_LABELS[w.work_type] || w.work_type }}</span>
          <span class="w-title">{{ w.title || '(未命名)' }}</span>
          <span v-if="w.exported_formats?.length" class="w-formats">
            已导出:<span v-for="f in w.exported_formats" :key="f" class="tag">{{ f.toUpperCase() }}</span>
          </span>
        </div>
        <div class="w-actions">
          <button v-if="w.story_id || w.work_type !== 'book'" class="btn sm" @click="openWork(w)">查看</button>
          <button class="btn ghost sm" @click="remove(w.id)">删除</button>
        </div>
        <div class="w-time">{{ w.created_at }}</div>
      </div>
    </div>

    <!-- 课件/环创 作品预览弹窗 -->
    <div v-if="viewWork" class="modal">
      <div class="modal-mask" @click="viewWork = null"></div>
      <div class="modal-dialog wide">
        <div class="modal-head">
          <div class="modal-title">{{ TYPE_LABELS[viewWork.work_type] }} · {{ viewWork.title }}</div>
          <span style="display:flex;gap:8px">
            <button class="btn sm" @click="exportPreview">🖼️ 导出PNG</button>
            <button class="modal-close" @click="viewWork = null">&times;</button>
          </span>
        </div>
        <div class="modal-body">
          <div ref="previewEl">
            <!-- 课件:结构化幻灯片只读渲染 -->
            <div v-if="viewWork.work_type === 'courseware'" class="pv-slides">
              <div v-for="(s, i) in (viewWork.content?.slides || [])" :key="i" class="pv-slide">
                <div class="pv-bar">
                  <span class="pv-badge">{{ s.title || '第 ' + (i + 1) + ' 页' }}</span>
                </div>
                <div class="pv-body">
                  <div v-if="s.subtitle" class="pv-sub">{{ s.subtitle }}</div>
                  <p v-if="s.text" class="pv-text">{{ s.text }}</p>
                  <div v-if="s.goal" class="pv-goal">{{ s.goal }}</div>
                  <p v-if="s.guide" class="pv-guide">{{ s.guide }}</p>
                  <div v-if="s.question" class="pv-question">{{ s.question }}</div>
                </div>
              </div>
            </div>
            <!-- 环创:卡片网格只读渲染 -->
            <div v-else-if="viewWork.work_type === 'artwork'" class="pv-grid">
              <div v-for="(c, i) in (viewWork.content?.cards || [])" :key="i" class="pv-card">
                <img v-if="c.image" class="pv-img" :src="imgSrc(c.image)" :alt="c.title || c.name" />
                <div v-if="c.kind === 'symbol'" class="pv-pattern">〰️🌀</div>
                <div class="pv-name">{{ c.name || c.title }}</div>
                <div class="pv-trace">{{ c.trace || c.text }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.works-view { max-width: 900px; margin: 0 auto; }
.view-title { font-size: 18px; color: var(--indigo-deep); margin-bottom: 14px; }
.loading-card { text-align: center; padding: 50px; color: var(--indigo-soft); }
.work-list { display: flex; flex-direction: column; gap: 10px; }
.work-item { display: flex; align-items: center; gap: 12px; position: relative; }
.w-info { display: flex; align-items: center; gap: 10px; flex: 1; flex-wrap: wrap; }
.w-title { font-size: 15px; font-weight: 700; color: var(--indigo-deep); }
.w-formats { font-size: 12px; color: #8a97ab; }
.w-actions { display: flex; gap: 8px; }
.w-time { position: absolute; bottom: 8px; right: 14px; font-size: 11px; color: #b3bdcc; }

/* 作品预览弹窗 */
.modal { position: fixed; inset: 0; z-index: 900; display: flex; align-items: center; justify-content: center; }
.modal-mask { position: absolute; inset: 0; background: rgba(29, 53, 87, 0.45); }
.modal-dialog {
  position: relative;
  width: min(860px, 94vw);
  max-height: 85vh;
  display: flex;
  flex-direction: column;
  background: #fbf7ef;
  border-radius: 16px;
  box-shadow: 0 24px 70px rgba(29, 53, 87, 0.35);
}
.modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  border-bottom: 1px solid #e7ecf4;
  background: #fff;
  border-radius: 16px 16px 0 0;
}
.modal-title { font-size: 16px; font-weight: 700; color: var(--indigo-deep); }
.modal-close { border: none; background: none; font-size: 22px; cursor: pointer; color: #9aa6b8; line-height: 1; }
.modal-body { padding: 16px 18px; overflow-y: auto; }

/* 课件幻灯片预览(只读) */
.pv-slides { display: flex; flex-direction: column; gap: 12px; background: #fff; padding: 14px; border-radius: 12px; }
.pv-slide { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 14px rgba(29, 53, 87, 0.12); }
.pv-bar { background: var(--indigo-deep); padding: 8px 14px; }
.pv-badge { color: #fff; font-size: 14px; font-weight: 700; letter-spacing: 1px; }
.pv-body { padding: 12px 14px; }
.pv-sub { font-size: 13px; color: var(--indigo-soft); text-align: center; margin-bottom: 8px; }
.pv-text { font-size: 15px; line-height: 1.8; color: var(--ink); white-space: pre-line; margin-bottom: 8px; }
.pv-goal { background: #e8f0fb; border-radius: 8px; padding: 7px 10px; font-size: 12.5px; color: var(--indigo); margin-bottom: 8px; }
.pv-guide { font-size: 12.5px; color: #8a97ab; font-style: italic; margin-bottom: 6px; }
.pv-question { background: #fdf6e3; border-radius: 8px; padding: 7px 10px; font-size: 13px; color: #8a6a2f; font-weight: 600; }

/* 环创卡片预览(只读) */
.pv-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; background: #fff; padding: 14px; border-radius: 12px; }
.pv-card {
  border: 2px dashed #c9d3e3;
  border-radius: 12px;
  padding: 12px;
  min-height: 140px;
  background: #fbfaf6;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
}
.pv-img { max-width: 100%; max-height: 100px; border-radius: 8px; margin-bottom: 6px; object-fit: cover; }
.pv-pattern { font-size: 20px; letter-spacing: 4px; color: var(--indigo); margin-bottom: 4px; }
.pv-name { font-size: 15px; font-weight: 700; color: var(--indigo-deep); margin-bottom: 4px; }
.pv-trace { font-size: 12px; color: var(--indigo-soft); line-height: 1.6; }
</style>
