import { defineConfig, type DefaultTheme } from 'vitepress'
import { withMermaid } from 'vitepress-plugin-mermaid'
import { cpSync, existsSync, statSync } from 'node:fs'
import { dirname, relative, resolve, sep } from 'node:path'
import { fileURLToPath } from 'node:url'

const docsRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const repoUrl = 'https://github.com/habibGamal/turbo_tenant'
const sourceBranch = 'refactoring'

/**
 * The handbook links straight to source files (../../app/...), which the
 * docs site does not serve. Point those links at GitHub so they keep working,
 * and open the generated architecture explorer (plain HTML) outside the SPA router.
 */
function sourceLinks(md: any) {
  const defaultRender =
    md.renderer.rules.link_open ??
    ((tokens: any, idx: number, options: any, _env: any, self: any) => self.renderToken(tokens, idx, options))

  md.renderer.rules.link_open = (tokens: any, idx: number, options: any, env: any, self: any) => {
    const token = tokens[idx]
    const href: string | null = token.attrGet('href')
    if (href && env?.path && !/^([a-z]+:|#|\/)/i.test(href)) {
      const [target, hash = ''] = href.split('#')
      const absolute = resolve(dirname(env.path), decodeURI(target))
      const inDocs = absolute === docsRoot || absolute.startsWith(docsRoot + sep)
      const rel = relative(docsRoot, absolute).split(sep).join('/')

      if (inDocs && rel.startsWith('engineering-atlas/') && rel.endsWith('.html')) {
        token.attrSet('href', '/' + rel)
        token.attrSet('target', '_blank')
      } else if (!inDocs || !(target.endsWith('.md') || target === '' || isDocsDir(absolute))) {
        const repoPath = relative(resolve(docsRoot, '..'), absolute).split(sep).join('/')
        token.attrSet('href', `${repoUrl}/blob/${sourceBranch}/${repoPath}${hash ? '#' + hash : ''}`)
        token.attrSet('target', '_blank')
        token.attrSet('rel', 'noreferrer')
      }
    }
    return defaultRender(tokens, idx, options, env, self)
  }
}

function isDocsDir(path: string) {
  return existsSync(path) && statSync(path).isDirectory() && existsSync(resolve(path, 'README.md'))
}

const technicalSidebar: DefaultTheme.Sidebar = [
  {
    text: 'Start here',
    items: [
      { text: 'Handbook home', link: '/handbook' },
      { text: 'Product overview', link: '/overview' },
      { text: 'Getting started', link: '/getting-started' },
    ],
  },
  {
    text: 'Architecture',
    items: [
      { text: 'Architecture', link: '/architecture' },
      { text: 'Data model', link: '/data-model' },
      { text: 'Plugin configuration pattern', link: '/architecture/plugin-configuration-pattern' },
      { text: 'SaaS lifecycle engine', link: '/architecture/saas-lifecycle-engine' },
    ],
  },
  {
    text: 'POS ↔ website',
    items: [
      { text: 'Connecting a POS (end to end)', link: '/pos-website-link' },
      { text: 'POS integration plugin', link: '/features/pos-integration' },
      { text: 'Delivery monitor design', link: '/order-pos-delivery-monitor' },
    ],
  },
  {
    text: 'Features',
    collapsed: false,
    items: [
      { text: 'Feature catalog', link: '/features/' },
      { text: 'Store provisioning', link: '/features/tenant-provisioning' },
      { text: 'Plugin marketplace', link: '/features/plugin-marketplace' },
      { text: 'Store settings', link: '/features/store-settings' },
      { text: 'Customer accounts', link: '/features/customer-accounts' },
      { text: 'Catalog and products', link: '/features/catalog' },
      { text: 'Combo packages', link: '/features/combo-packages' },
      { text: 'Media optimizer', link: '/features/media-optimizer' },
      { text: 'Cart', link: '/features/cart' },
      { text: 'Checkout and orders', link: '/features/checkout-and-orders' },
      { text: 'Delivery areas & branches', link: '/features/locations-delivery' },
      { text: 'Guest checkout', link: '/features/guest-checkout' },
      { text: 'Online payments', link: '/features/payments' },
      { text: 'Coupons', link: '/features/coupons' },
      { text: 'Favorites', link: '/features/favorites' },
      { text: 'Product reviews', link: '/features/product-reviews' },
      { text: 'Hero banners', link: '/features/hero-banners' },
      { text: 'CMS pages', link: '/features/cms-pages' },
      { text: 'Push notifications', link: '/features/push-notifications' },
      { text: 'Meta marketing', link: '/features/meta-marketing' },
      { text: 'Sample greeting', link: '/features/sample-greeting' },
    ],
  },
  {
    text: 'Integrate & configure',
    items: [
      { text: 'Interfaces (HTTP, events)', link: '/interfaces' },
      { text: 'Configuration', link: '/configuration' },
    ],
  },
  {
    text: 'Run it',
    items: [
      { text: 'Deployment', link: '/deployment' },
      { text: 'Production server & post-pull', link: '/production-server' },
      { text: 'Queue, workers & scheduler', link: '/queue-and-workers' },
      { text: 'Operations & troubleshooting', link: '/operations' },
    ],
  },
  {
    text: 'Develop',
    items: [
      { text: 'Development guide', link: '/development' },
      { text: 'Documentation coverage', link: '/coverage' },
      { text: 'Docs site', link: '/docs-site' },
    ],
  },
  {
    text: 'Design notes & plans',
    collapsed: true,
    items: [
      { text: 'Kashier gateway', link: '/kashier-payment-gateway' },
      { text: 'Meta Pixel & CAPI plan', link: '/META_PIXEL_AND_CAPI_PLAN' },
      { text: 'Guest ordering plan', link: '/GUEST_ORDERING_PLAN' },
      { text: 'Custom pages guide', link: '/CUSTOM_PAGES_GUIDE' },
      { text: 'Storefront performance', link: '/storefront-performance' },
      { text: 'Theme test ids', link: '/THEME_TEST_IDS_SPEC' },
    ],
  },
]

const arabicSidebar: DefaultTheme.Sidebar = [
  {
    text: 'البداية',
    items: [
      { text: 'مرحباً بك', link: '/ar/' },
      { text: 'أول خطوات تشغيل المتجر', link: '/ar/getting-started' },
      { text: 'سوق الإضافات', link: '/ar/plugins' },
      { text: 'إعدادات المتجر', link: '/ar/store-settings' },
    ],
  },
  {
    text: 'المنيو (الكتالوج)',
    items: [
      { text: 'الفئات والمنتجات', link: '/ar/catalog' },
      { text: 'التنويعات (الأحجام)', link: '/ar/variants' },
      { text: 'الخيارات الإضافية', link: '/ar/extras' },
      { text: 'البيع بالوزن', link: '/ar/weight-options' },
      { text: 'أقسام الواجهة', link: '/ar/sections' },
      { text: 'باقات التوفير', link: '/ar/packages' },
    ],
  },
  {
    text: 'التوصيل والطلبات',
    items: [
      { text: 'الفروع والمحافظات والمناطق', link: '/ar/branches-delivery' },
      { text: 'إدارة الطلبات', link: '/ar/orders' },
      { text: 'الربط مع نقطة البيع (POS)', link: '/ar/pos' },
      { text: 'الدفع الإلكتروني', link: '/ar/payments' },
    ],
  },
  {
    text: 'التسويق والعملاء',
    items: [
      { text: 'الكوبونات', link: '/ar/coupons' },
      { text: 'السلايدر وصفحات المحتوى', link: '/ar/content' },
      { text: 'الإشعارات', link: '/ar/notifications' },
      { text: 'ميتا (فيسبوك) والكتالوج', link: '/ar/meta' },
      { text: 'العملاء والتقييمات', link: '/ar/customers' },
    ],
  },
  {
    text: 'مساعدة',
    items: [
      { text: 'تجربة العميل على الموقع', link: '/ar/customer-journey' },
      { text: 'أسئلة شائعة وحل المشكلات', link: '/ar/faq' },
    ],
  },
]

export default withMermaid(
  defineConfig({
    title: 'Turbo Tenant',
    description: 'Multi-tenant online ordering for restaurants: technical handbook and store admin guide',
    cleanUrls: true,
    lastUpdated: true,
    srcExclude: ['node_modules/**', 'engineering-atlas/**', 'README.md.bak'],
    rewrites: {
      'README.md': 'handbook.md',
      'features/README.md': 'features/index.md',
    },
    // Links to ../CHANGELOG.md etc. are rewritten to GitHub by sourceLinks().
    ignoreDeadLinks: [/engineering-atlas/],
    head: [
      ['link', { rel: 'icon', href: '/logo.svg', type: 'image/svg+xml' }],
      ['link', { rel: 'preconnect', href: 'https://fonts.googleapis.com' }],
      ['link', { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' }],
      [
        'link',
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap',
        },
      ],
    ],
    markdown: {
      config: (md) => sourceLinks(md),
    },
    locales: {
      root: {
        label: 'English (technical)',
        lang: 'en',
        themeConfig: {
          nav: [
            { text: 'Handbook', link: '/handbook' },
            { text: 'POS link', link: '/pos-website-link' },
            { text: 'Features', link: '/features/' },
            { text: 'Deployment', link: '/deployment' },
            { text: 'دليل المستخدم', link: '/ar/' },
          ],
          sidebar: technicalSidebar,
          outline: { level: [2, 3], label: 'On this page' },
        },
      },
      ar: {
        label: 'العربية (دليل المستخدم)',
        lang: 'ar',
        dir: 'rtl',
        link: '/ar/',
        title: 'توربو — دليل المتجر',
        description: 'دليل استخدام لوحة إدارة متجر الطلب أونلاين',
        themeConfig: {
          nav: [
            { text: 'الدليل', link: '/ar/getting-started' },
            { text: 'المنيو', link: '/ar/catalog' },
            { text: 'الطلبات', link: '/ar/orders' },
            { text: 'نقطة البيع', link: '/ar/pos' },
            { text: 'Technical docs', link: '/' },
          ],
          sidebar: arabicSidebar,
          outline: { level: [2, 3], label: 'في هذه الصفحة' },
          docFooter: { prev: 'السابق', next: 'التالي' },
          darkModeSwitchLabel: 'المظهر',
          lightModeSwitchTitle: 'الوضع الفاتح',
          darkModeSwitchTitle: 'الوضع الداكن',
          sidebarMenuLabel: 'القائمة',
          returnToTopLabel: 'العودة للأعلى',
          langMenuLabel: 'اللغة',
          lastUpdated: { text: 'آخر تحديث' },
          notFound: {
            title: 'الصفحة غير موجودة',
            quote: 'الرابط الذي فتحته غير موجود في الدليل.',
            linkText: 'العودة للرئيسية',
          },
        },
      },
    },
    themeConfig: {
      logo: '/logo.svg',
      search: {
        provider: 'local',
        options: {
          locales: {
            ar: {
              translations: {
                button: { buttonText: 'بحث', buttonAriaLabel: 'بحث' },
                modal: {
                  noResultsText: 'لا توجد نتائج لـ',
                  resetButtonTitle: 'مسح',
                  footer: { selectText: 'اختيار', navigateText: 'تنقل', closeText: 'إغلاق' },
                },
              },
            },
          },
        },
      },
      socialLinks: [{ icon: 'github', link: repoUrl }],
      editLink: {
        pattern: `${repoUrl}/edit/${sourceBranch}/docs/:path`,
        text: 'Edit this page on GitHub',
      },
    },
    mermaid: { themeVariables: { fontFamily: "'IBM Plex Sans Arabic', Inter, sans-serif" } },
    vite: {
      // Inline PostCSS config so Vite does not load the application's postcss.config.js (Tailwind) from the repo root.
      css: { postcss: {} },
      build: { chunkSizeWarningLimit: 4000 }, // mermaid is large
    },
    buildEnd(site) {
      // Ship the generated architecture explorer next to the site.
      const atlas = resolve(docsRoot, 'engineering-atlas')
      if (existsSync(atlas)) cpSync(atlas, resolve(site.outDir, 'engineering-atlas'), { recursive: true })
    },
  }),
)
