# Docs site

The handbook (English, technical) and the store admin guide (Arabic, RTL) are plain Markdown in `docs/`, readable on GitHub as they are, and built into one searchable website with [VitePress](https://vitepress.dev).

| Path | Content |
| --- | --- |
| `docs/*.md`, `docs/features/`, `docs/architecture/` | Technical handbook (site root `/`, handbook index at `/handbook`) |
| `docs/ar/` | Arabic user guide for store admins (`/ar/`, right-to-left) |
| `docs/.vitepress/config.mts` | Navigation, sidebars, locales, search, link rewriting |
| `docs/.vitepress/theme/custom.css` | Brand colors, Arabic font (IBM Plex Sans Arabic), RTL fixes, step cards |
| `docs/public/` | Static files (logo) |
| `docs/engineering-atlas/` | Generated architecture explorer, copied into the build as-is |

The docs site has its own `package.json` in `docs/`, so it does not touch the application's dependencies.

```bash
cd docs
npm install
npm run dev       # http://localhost:5173, live reload
npm run build     # static site in docs/.vitepress/dist
npm run preview   # serve the build
```

Deploy `docs/.vitepress/dist` to any static host (Nginx, GitHub Pages, Cloudflare Pages, Netlify). With Nginx, serve the folder as the document root and add `try_files $uri $uri.html $uri/ =404;` because the site uses clean URLs.

## Writing conventions

- Links between pages are relative `.md` links, so they work on GitHub and on the site. Links to source files (`../app/...`) are rewritten to GitHub (`refactoring` branch) when the site is built.
- Diagrams use ```` ```mermaid ```` blocks.
- Arabic pages: put admin menu paths in `<span class="path">الكتالوج والمنتجات › المنتجات</span>`, and wrap numbered procedures in `<div class="steps">` … `</div>` (blank lines around the list) to render them as step cards. Use VitePress callouts (`::: tip`, `::: warning`, `::: danger`) with an Arabic title, for example `::: tip نصيحة`.
- Every new page is linked from the handbook index or the Arabic sidebar, and `python .agents/skills/engineering-atlas/scripts/docs_check.py .` must pass.
