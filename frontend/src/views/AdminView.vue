<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import { toastOk, toastErr } from '../composables/useToast'
import { useAuthStore } from '../stores/auth'
import { STATUS_LABELS, STATUS_COLORS } from '../utils/maps'

// 审核后台:概览 / 审核队列 / AI 日志 / 批量导入
const router = useRouter()
const auth = useAuthStore()
const tab = ref('review')

const stats = ref(null)
const queue = ref([])
const aiLogs = ref([])
const importText = ref('')
const importType = ref('materials')
const importing = ref(false)

onMounted(async () => {
  if (!auth.hasRole('admin', 'reviewer')) {
    router.replace('/login')
    return
  }
  loadStats()
  loadQueue()
  loadLogs()
})

async function loadStats() {
  try { stats.value = await api.stats() } catch (e) { toastErr(e.message) }
}
async function loadQueue() {
  try { queue.value = (await api.reviewQueue({ page_size: 100 })).list } catch (e) { toastErr(e.message) }
}
async function loadLogs() {
  try { aiLogs.value = (await api.aiLogs({ page_size: 50 })).list } catch (e) { toastErr(e.message) }
}

async function review(id, action) {
  let note = ''
  if (action === 'reject') {
    const input = prompt('驳回意见(可留空):')
    if (input === null) return // 取消
    note = input
  }
  try {
    await api.review('story', id, { action, note })
    toastOk(action === 'approve' ? '已通过,进入图书馆' : '已驳回')
    loadQueue()
    loadStats()
  } catch (e) {
    toastErr(e.message)
  }
}

async function exportCsv() {
  try {
    const blob = await api.aiLogsExport()
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = 'tonghua-canger-ai-logs.csv'
    a.click()
    URL.revokeObjectURL(a.href)
    toastOk('CSV 已下载(AIGC 披露材料)')
  } catch (e) {
    toastErr(e.message)
  }
}

async function doImport() {
  importing.value = true
  try {
    const rows = importText.value
      .split('\n')
      .map((l) => l.trim())
      .filter(Boolean)
      .map((l) => JSON.parse(l))
    const res = await api.importRows({ type: importType.value, rows })
    toastOk(`导入成功 ${res.imported} 条`)
    importText.value = ''
    loadStats()
  } catch (e) {
    toastErr('导入失败(每行一个 JSON 对象):' + e.message)
  } finally {
    importing.value = false
  }
}
</script>

<template>
  <div class="admin-view">
    <h2 class="view-title">🛡️ 审核后台</h2>
    <nav class="lib-tabs card">
      <button class="ltab" :class="{ active: tab === 'review' }" @click="tab = 'review'">📋 审核队列</button>
      <button class="ltab" :class="{ active: tab === 'stats' }" @click="tab = 'stats'">📊 概览</button>
      <button class="ltab" :class="{ active: tab === 'logs' }" @click="tab = 'logs'">🤖 AI 日志</button>
      <button class="ltab" :class="{ active: tab === 'import' }" @click="tab = 'import'">📥 批量导入</button>
    </nav>

    <!-- 审核队列 -->
    <div v-if="tab === 'review'">
      <div v-if="queue.length === 0" class="card empty-state">
        <div class="empty-icon">✅</div>
        <div class="empty-title">没有待审核的绘本</div>
        <div class="empty-sub">教师提交审核后会出现在这里</div>
      </div>
      <div v-else class="queue-list">
        <div v-for="s in queue" :key="s.id" class="queue-item card">
          <div class="q-info">
            <router-link :to="`/stories/${s.id}`" class="q-title">《{{ s.title }}》</router-link>
            <span class="badge">{{ s.theme }}</span>
            <span class="badge">{{ s.age_band }} 岁</span>
            <span class="badge">{{ s.page_count }} 页</span>
            <span v-if="s.ai_generated" class="badge ai-badge">AI 生成</span>
            <span class="q-version">v{{ s.version }}</span>
            <div class="q-note" v-if="s.review_note">上轮驳回意见:{{ s.review_note }}</div>
          </div>
          <div class="q-actions">
            <router-link :to="`/stories/${s.id}`"><button class="btn ghost sm">👁 预览</button></router-link>
            <button class="btn danger sm" @click="review(s.id, 'reject')">驳回</button>
            <button class="btn ok sm" @click="review(s.id, 'approve')">✅ 通过</button>
          </div>
        </div>
      </div>
    </div>

    <!-- 概览 -->
    <div v-else-if="tab === 'stats' && stats" class="stats-grid">
      <div class="stat card"><div class="stat-num">{{ stats.materials }}</div><div class="stat-label">素材(条)</div></div>
      <div class="stat card"><div class="stat-num">{{ stats.symbols }}</div><div class="stat-label">文化符号</div></div>
      <div class="stat card"><div class="stat-num">{{ stats.stories_total }}</div><div class="stat-label">绘本总数</div></div>
      <div class="stat card"><div class="stat-num">{{ stats.works }}</div><div class="stat-label">作品记录</div></div>
      <div class="stat card"><div class="stat-num">{{ stats.ai_calls_total }}</div><div class="stat-label">AI 调用总数</div></div>
      <div class="stat card"><div class="stat-num">{{ stats.ai_success_today }}</div><div class="stat-label">今日 AI 成功</div></div>
      <div class="stat card"><div class="stat-num">{{ stats.ai_fallback_total }}</div><div class="stat-label">降级拼装(0成本)</div></div>
      <div class="stat card wide">
        <div class="stat-label">绘本状态分布</div>
        <div class="stat-status">
          <span v-for="(c, s) in stats.stories_by_status" :key="s" class="badge" :style="{ background: STATUS_COLORS[s] + '22', color: STATUS_COLORS[s] }">
            {{ STATUS_LABELS[s] }} × {{ c }}
          </span>
        </div>
      </div>
    </div>

    <!-- AI 日志 -->
    <div v-else-if="tab === 'logs'">
      <div class="log-bar">
        <button class="btn sm" @click="loadLogs">🔄 刷新</button>
        <button class="btn ok sm" @click="exportCsv">⬇️ 导出 CSV(AIGC 披露)</button>
        <span class="count-tip">{{ aiLogs.length }} 条记录</span>
      </div>
      <div class="log-table card">
        <table>
          <thead>
            <tr>
              <th>ID</th><th>用途</th><th>模型</th><th>状态</th>
              <th>输入tokens</th><th>输出tokens</th><th>耗时ms</th><th>目标</th><th>时间</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="l in aiLogs" :key="l.id">
              <td>{{ l.id }}</td>
              <td>{{ l.purpose }}</td>
              <td>{{ l.model }}</td>
              <td>
                <span class="badge" :class="'log-' + l.status">
                  {{ { success: '成功', fallback: '降级', error: '失败' }[l.status] }}
                </span>
              </td>
              <td>{{ l.input_tokens }}</td>
              <td>{{ l.output_tokens }}</td>
              <td>{{ l.latency_ms }}</td>
              <td>{{ l.target_type }}{{ l.target_id ? '#' + l.target_id : '' }}</td>
              <td class="l-time">{{ l.created_at }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 批量导入 -->
    <div v-else class="card import-panel">
      <p class="import-tip">
        每行一个 JSON 对象。素材示例:<br />
        <code>{"material_type":"非遗工艺","name":"白族三道茶","age_band":"5-6","description":"…","image_prompt":"…","edu_goals":[{"domain":"社会","goal":"…"}],"tags":["#三道茶"]}</code><br />
        符号示例:<br />
        <code>{"name":"照壁","category":"建筑","description":"…","symbol_trace":"…","source":"…","image_prompt":"…"}</code>
      </p>
      <div class="field">
        <label>类型</label>
        <select v-model="importType">
          <option value="materials">素材</option>
          <option value="symbols">文化符号</option>
        </select>
      </div>
      <div class="field">
        <label>数据(JSON Lines)</label>
        <textarea v-model="importText" rows="10" placeholder='{"name":"…",…}'></textarea>
      </div>
      <button class="btn" :disabled="importing" @click="doImport">{{ importing ? '导入中…' : '📥 导入' }}</button>
    </div>
  </div>
</template>

<style scoped>
.admin-view { max-width: 1060px; margin: 0 auto; }
.view-title { font-size: 18px; color: var(--indigo-deep); margin-bottom: 14px; }
.lib-tabs { display: flex; gap: 4px; margin-bottom: 14px; }
.ltab {
  border: none;
  background: none;
  padding: 10px 18px;
  font-size: 14px;
  cursor: pointer;
  color: var(--indigo-soft);
  border-radius: 10px;
}
.ltab.active { background: var(--indigo); color: #fff; font-weight: 700; }
.queue-list { display: flex; flex-direction: column; gap: 10px; }
.queue-item { display: flex; align-items: center; gap: 14px; }
.q-info { flex: 1; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.q-title { font-size: 15px; font-weight: 700; }
.ai-badge { background: #e8f0fb; color: var(--indigo); }
.q-version { font-size: 11px; color: #9aa6b8; }
.q-note { width: 100%; font-size: 12px; color: var(--danger); }
.q-actions { display: flex; gap: 8px; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
.stat { text-align: center; padding: 22px 10px; }
.stat-num { font-size: 30px; font-weight: 800; color: var(--indigo); }
.stat-label { font-size: 13px; color: var(--indigo-soft); margin-top: 4px; }
.stat.wide { grid-column: 1 / -1; text-align: left; }
.stat-status { display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap; }
.log-bar { display: flex; gap: 10px; align-items: center; margin-bottom: 12px; }
.count-tip { font-size: 12px; color: #9aa6b8; }
.log-table { overflow-x: auto; padding: 6px; }
table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
th, td { padding: 7px 9px; text-align: left; border-bottom: 1px solid #eef2f8; white-space: nowrap; }
th { color: var(--indigo-soft); font-weight: 600; }
.log-success { background: #e3f0e8; color: #2b8a5c; }
.log-fallback { background: #f3ead8; color: #8a6a2f; }
.log-error { background: #f9e6e5; color: #c0504d; }
.l-time { color: #9aa6b8; }
.import-panel { max-width: 720px; }
.import-tip { font-size: 13px; color: var(--indigo-soft); line-height: 2; margin-bottom: 14px; }
.import-tip code { background: #eef3fb; padding: 2px 6px; border-radius: 6px; font-size: 12px; }
</style>
