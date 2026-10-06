import axios from 'axios'

const http = axios.create({ baseURL: '/api', timeout: 90000 })

http.interceptors.request.use((cfg) => {
  const t = localStorage.getItem('canger_token')
  if (t) cfg.headers.Authorization = `Bearer ${t}`
  return cfg
})

http.interceptors.response.use(
  (res) => {
    // blob 下载(如 CSV 导出)直接透传
    if (res.config.responseType === 'blob') return res.data
    const d = res.data
    if (d && d.code === 0) return d.data
    return Promise.reject(new Error(d?.message || '请求失败'))
  },
  (err) => {
    const msg = err.response?.data?.message || err.message || '网络错误'
    const e = new Error(msg)
    e.status = err.response?.status
    return Promise.reject(e)
  }
)

export const api = {
  // 健康 / 认证
  health: () => http.get('/health'),
  login: (username, password) => http.post('/auth/login', { username, password }),
  me: () => http.get('/auth/me'),
  // 素材
  materials: (params) => http.get('/materials', { params }),
  material: (id) => http.get(`/materials/${id}`),
  createMaterial: (data) => http.post('/materials', data),
  updateMaterial: (id, data) => http.put(`/materials/${id}`, data),
  deleteMaterial: (id) => http.delete(`/materials/${id}`),
  // 符号
  symbols: (params) => http.get('/symbols', { params }),
  createSymbol: (data) => http.post('/symbols', data),
  updateSymbol: (id, data) => http.put(`/symbols/${id}`, data),
  deleteSymbol: (id) => http.delete(`/symbols/${id}`),
  // 故事
  stories: (params) => http.get('/stories', { params }),
  story: (id) => http.get(`/stories/${id}`),
  updateStory: (id, data) => http.put(`/stories/${id}`, data),
  submitStory: (id) => http.post(`/stories/${id}/submit`),
  cloneStory: (id) => http.post(`/stories/${id}/clone`),
  deleteStory: (id) => http.delete(`/stories/${id}`),
  // 作品
  works: (params) => http.get('/works', { params }),
  saveWork: (data) => http.post('/works', data),
  updateWork: (id, data) => http.put(`/works/${id}`, data),
  deleteWork: (id) => http.delete(`/works/${id}`),
  upload: (data) => http.post('/upload', data, { timeout: 60000 }),
  // AI
  generateStory: (data) => http.post('/ai/generate/story', data),
  polish: (data) => http.post('/ai/polish', data),
  generateImage: (data) => http.post('/ai/generate-image', data, { timeout: 150000 }),
  extractCharacters: (data) => http.post('/ai/extract-characters', data),
  setCharacterImage: (data) => http.post('/ai/set-character-image', data),
  tts: (data) => http.post('/ai/tts', data),
  quota: () => http.get('/ai/quota'),
  // 后台
  reviewQueue: (params) => http.get('/admin/review/queue', { params }),
  review: (type, id, data) => http.post(`/admin/review/${type}/${id}`, data),
  aiLogs: (params) => http.get('/admin/ai-logs', { params }),
  aiLogsExport: () => http.get('/admin/ai-logs/export', { responseType: 'blob' }),
  stats: () => http.get('/admin/stats'),
  importRows: (data) => http.post('/admin/import', data),
}

export default http
