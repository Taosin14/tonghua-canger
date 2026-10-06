import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  { path: '/', component: () => import('../views/WorkbenchView.vue') },
  { path: '/library', component: () => import('../views/LibraryView.vue') },
  { path: '/stories/:id', component: () => import('../views/BookReaderView.vue') },
  { path: '/stories/:id/edit', component: () => import('../views/BookEditorView.vue') },
  { path: '/works', component: () => import('../views/WorksView.vue') },
  { path: '/admin', component: () => import('../views/AdminView.vue'), meta: { requiresRole: ['admin', 'reviewer'] } },
  { path: '/login', component: () => import('../views/LoginView.vue') },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.meta.requiresRole && !auth.hasRole(...to.meta.requiresRole)) {
    return '/login'
  }
})

export default router
