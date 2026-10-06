// 演示截图:8 张关键界面(输出到 交付包/07-截图/)
import { chromium } from 'playwright-core'
import fs from 'fs'

const OUT = 'C:/Users/Lenovo/Desktop/童话苍洱/交付包/07-截图'
fs.mkdirSync(OUT, { recursive: true })

const browser = await chromium.launch({ channel: 'msedge', headless: true })
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } })

// 先用 API 登录拿 token,注入 localStorage(访问审核后台)
const loginRes = await fetch('http://127.0.0.1:8080/api/auth/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ username: 'admin', password: 'admin123' }),
})
const { data } = await loginRes.json()

async function shot(name, path, prepare) {
  try {
    await page.goto('http://localhost:5173' + path, { waitUntil: 'networkidle', timeout: 30000 })
    await page.addInitScript(() => {}) // noop
    await page.evaluate((t) => {
      localStorage.setItem('canger_token', t)
      localStorage.setItem('canger_user', JSON.stringify({ id: 1, username: 'admin', role: 'admin', display_name: '审核管理员' }))
    }, data.token)
    await page.reload({ waitUntil: 'networkidle' })
    if (prepare) await prepare()
    await page.waitForTimeout(1500)
    await page.screenshot({ path: `${OUT}/${name}.png` })
    console.log('OK', name)
  } catch (e) {
    console.log('FAIL', name, e.message.split('\n')[0].slice(0, 120))
  }
}

await shot('工作台', '/')
await shot('图书馆', '/library')
await shot('阅读器', '/stories/1', async () => {
  await page.click('button:has-text("下一页")')
  await page.waitForTimeout(800)
})
await shot('编辑器', '/stories/7/edit')
await shot('文化库-符号', '/library', async () => {
  await page.click('button:has-text("文化符号库")')
  await page.waitForTimeout(800)
})
await shot('审核后台-AI日志', '/admin', async () => {
  await page.click('button:has-text("AI 日志")')
  await page.waitForTimeout(800)
})
await shot('审核后台-概览', '/admin', async () => {
  await page.click('button:has-text("概览")')
  await page.waitForTimeout(800)
})
await shot('生图对比-扎染', '/stories/7', async () => {
  await page.click('button:has-text("下一页")')
  await page.waitForTimeout(800)
})

await browser.close()
console.log('done')
