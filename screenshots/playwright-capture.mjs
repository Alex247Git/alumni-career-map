#!/usr/bin/env node
/**
 * Playwright capture script — Alumni Career Map demo screenshots.
 *
 * Prereqs:
 *   1. Docker stack running:   docker compose up -d --build
 *   2. npm i playwright (once) + npx playwright install chromium
 *
 * Usage:
 *   node playwright-capture.mjs            # capture all shots into ./out
 *   node playwright-capture.mjs --ci        # CI mode: use CDP/headless flags, fail on error
 *
 * Output: ./out/*.png  (gitignored). Pick the ones you like and copy to ./demo/*.png.
 */
import { chromium } from 'playwright'
import { mkdirSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const BASE_URL = process.env.ALUMNI_BASE_URL || 'http://localhost:8081'
const OUT_DIR = path.join(__dirname, 'out')

const viewport = { width: 1440, height: 900 }

// ---------- helpers ----------
async function ensureDir() {
  mkdirSync(OUT_DIR, { recursive: true })
}

async function shot(page, name, { fullPage = false } = {}) {
  const target = path.join(OUT_DIR, `${name}.png`)
  await page.screenshot({ path: target, fullPage })
  console.log(`✔ ${name}.png`)
  return target
}

// ---------- capture flow ----------
async function main() {
  await ensureDir()
  const browser = await chromium.launch({ headless: !process.env.PLAYWRIGHT_HEADFUL })
  const ctx = await browser.newContext({ viewport, locale: 'en-US' })
  const page = await ctx.newPage()

  try {
    // 1) Landing page — interactive map + search form
    await page.goto(`${BASE_URL}/`, { waitUntil: 'networkidle', timeout: 30000 })
    await page.waitForTimeout(3500) // let Leaflet tiles + analytics render
    await shot(page, '01-landing-map', { fullPage: true })

    // 2) Open the login modal (screenshot the UI)…
    try {
      await page.click('#loginBtn')
      await page.waitForTimeout(800)
      await shot(page, '02-login-modal')
    } catch {
      console.error('login button not found — skipping shot 02')
    }

    // …then actually log in (demo credentials from seed.sql) so the
    // subsequent search succeeds with a valid JWT instead of erroring.
    try {
      await page.fill('#loginEmail', 'gpapadop@example.com')
      await page.fill('#loginPassword', 'alumni2026')
      await page.click('#loginForm button[type="submit"]')
      // wait until the modal closes = login completed
      await page.waitForSelector('#loginModal.show', { state: 'detached', timeout: 15000 }).catch(() => {})
      await page.waitForTimeout(1500)
    } catch (e) {
      console.error('login flow failed:', e.message)
    }

    // 3) Run a search (name "Pap") to show results + map pins
    try {
      await page.fill('#searchName', 'Pap')
      await page.click('button[type="submit"]')
      await page.waitForTimeout(3500) // results render + error/success toast auto-hides
      await shot(page, '03-search-results', { fullPage: true })
    } catch {
      console.error('search form not found — skipping shot 03')
    }

    console.log('\nDirectory out/:')
    console.log(`- ${OUT_DIR}`)
  } finally {
    await browser.close()
  }
}

main().catch((e) => {
  console.error('Capture failed:', e)
  process.exit(1)
})