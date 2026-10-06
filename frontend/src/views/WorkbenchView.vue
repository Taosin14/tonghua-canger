<script setup>
import { ref } from 'vue'
import Sidebar from '../components/layout/Sidebar.vue'
import BookConfigPanel from '../components/book/BookConfigPanel.vue'
import BookPreview from '../components/book/BookPreview.vue'
import ExportBar from '../components/export/ExportBar.vue'
import CoursewarePanel from '../components/courseware/CoursewarePanel.vue'
import ArtworkPanel from '../components/artwork/ArtworkPanel.vue'
import { useThemeStore } from '../stores/theme'
import { useRouter } from 'vue-router'

// 主工作台:三栏(主题导航 | 工坊 Tab | 预览/编辑/导出)
const tab = ref('book')
const previewTab = ref('preview')
const themeStore = useThemeStore()
const router = useRouter()

const TABS = [
  { key: 'book', icon: '📖', label: '绘本工坊' },
  { key: 'courseware', icon: '🎓', label: '课件工坊' },
  { key: 'artwork', icon: '🎨', label: '环创素材' },
]
</script>

<template>
  <div class="workbench">
    <Sidebar />

    <section class="workspace card">
      <nav class="tabs">
        <button
          v-for="t in TABS"
          :key="t.key"
          class="tab"
          :class="{ active: tab === t.key }"
          @click="tab = t.key"
        >
          <span class="tab-icon">{{ t.icon }}</span>
          <span>{{ t.label }}</span>
        </button>
      </nav>

      <div class="tab-panels">
        <div v-show="tab === 'book'" class="panel">
          <h2 class="panel-title">📖 绘本工坊 <span class="panel-sub">选主题 → 配参数 → 一键生成</span></h2>
          <BookConfigPanel />
        </div>
        <div v-show="tab === 'courseware'" class="panel">
          <h2 class="panel-title">🎓 课件工坊 <span class="panel-sub">绘本 → 教学课件,教师可逐页修改</span></h2>
          <CoursewarePanel />
        </div>
        <div v-show="tab === 'artwork'" class="panel">
          <h2 class="panel-title">🎨 环创素材 <span class="panel-sub">文化纹样卡 + 教师手动上传,可打印布置教室</span></h2>
          <ArtworkPanel />
        </div>
      </div>
    </section>

    <aside class="previewpane card">
      <div class="preview-header">
        <div class="preview-tabs">
          <button
            v-for="p in [
              { key: 'preview', label: '预览' },
              { key: 'edit', label: '编辑' },
              { key: 'export', label: '导出' },
            ]"
            :key="p.key"
            class="ptab"
            :class="{ active: previewTab === p.key }"
            @click="previewTab = p.key"
          >{{ p.label }}</button>
        </div>
      </div>
      <div class="preview-body">
        <template v-if="previewTab === 'preview'">
          <BookPreview />
        </template>
        <template v-else-if="previewTab === 'edit'">
          <div v-if="themeStore.currentStory?.id" class="edit-entry">
            <p>进入全屏编辑器,修改每页文本、教育目标、页序:</p>
            <button class="btn" @click="router.push(`/stories/${themeStore.currentStory.id}/edit`)">✏️ 打开编辑器</button>
          </div>
          <div v-else class="empty-state">
            <div class="empty-icon">✏️</div>
            <div class="empty-title">还没有可编辑的作品</div>
            <div class="empty-sub">先生成一本绘本,或从文化库打开一本</div>
          </div>
        </template>
        <template v-else>
          <ExportBar :story="themeStore.currentStory" />
        </template>
      </div>
    </aside>
  </div>
</template>

<style scoped>
.workbench {
  display: flex;
  gap: 14px;
  align-items: flex-start;
  max-width: 1380px;
  margin: 0 auto;
}
.workspace { flex: 1; min-width: 0; }
.tabs { display: flex; gap: 4px; border-bottom: 1px solid #e7ecf4; margin-bottom: 14px; }
.tab {
  display: flex;
  align-items: center;
  gap: 6px;
  border: none;
  background: none;
  padding: 10px 16px;
  font-size: 14px;
  cursor: pointer;
  color: var(--indigo-soft);
  border-bottom: 3px solid transparent;
  margin-bottom: -1px;
}
.tab:hover { color: var(--indigo); }
.tab.active { color: var(--indigo-deep); font-weight: 700; border-bottom-color: var(--indigo); }
.panel-title { font-size: 16px; color: var(--indigo-deep); margin-bottom: 16px; }
.panel-sub { font-size: 12px; color: #9aa6b8; font-weight: 400; margin-left: 8px; }
.previewpane { width: 300px; flex-shrink: 0; }
.preview-header { border-bottom: 1px solid #e7ecf4; margin-bottom: 12px; }
.preview-tabs { display: flex; gap: 4px; }
.ptab {
  border: none;
  background: none;
  padding: 8px 12px;
  font-size: 13px;
  cursor: pointer;
  color: var(--indigo-soft);
  border-bottom: 3px solid transparent;
  margin-bottom: -1px;
}
.ptab.active { color: var(--indigo-deep); font-weight: 700; border-bottom-color: var(--accent); }
.preview-body { min-height: 380px; }
.edit-entry p { font-size: 13px; color: var(--indigo-soft); margin-bottom: 12px; line-height: 1.7; }
@media (max-width: 960px) {
  .workbench { flex-direction: column; }
  .previewpane, .sidebar { width: 100%; }
}
</style>
