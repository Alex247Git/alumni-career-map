# 📸 Demo Screenshots

Automated demo shots for the **Alumni Career Map** profile section, captured with
[Playwright](https://playwright.dev) in a headless browser.

## Prereqs

```bash
# 1. Start the full Docker stack (MySQL + API + frontend)
docker compose up -d --build

# 2. Install Playwright once
cd screenshots
npm i
npx playwright install chromium
```

## Capture

```bash
npm run shot            # -> writes PNGs into ./out/
npm run shot:ci          # CI-friendly: fails on any error
```

Set a custom base URL (e.g. if the app isn't on port 8081):

```bash
ALUMNI_BASE_URL=http://localhost:8082 npm run shot
```

## What it captures

| Shot | File | Shows |
|------|------|-------|
| Landing | `01-landing-map.png` | Interactive Leaflet map + analytics + search form |
| Login | `02-login-modal.png` | JWT login modal (ownership-driven UI) |
| Search | `03-search-results.png` | Multi-criteria search results + map pins |

## Keeping screenshots in sync (CI)

To auto-refresh shots on every push, add the job to your workflow:

```yaml
- name: Refresh demo screenshots
  run: |
    cd screenshots
    npm ci
    npx playwright install chromium --with-deps
    npm run shot
```

> The `out/` dir is gitignored. Copy the shots you want to keep into `demo/` and commit them there.