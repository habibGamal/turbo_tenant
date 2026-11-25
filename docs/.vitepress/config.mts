import { existsSync, statSync } from 'node:fs'
import { dirname, relative, resolve, sep } from 'node:path'
import { fileURLToPath } from 'node:url'
import { defineConfig, type DefaultTheme } from 'vitepress'
import { withMermaid } from 'vitepress-plugin-mermaid'
import { productSwitcher } from './theme/products'

const isPublic = process.env.DOCS_PUBLIC === '1'

const docsRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const repoUrl = 'https://github.com/habibGamal/turbo_tenant'
const sourceBranch = 'refactoring'

const productUrls = {
  pos: process.env.DOCS_POS_URL,
  website: process.env.DOCS_WEBSITE_URL,
  platform: process.env.DOCS_PLATFORM_URL,
}

function isDocsDir(path: string): boolean {
  return (
    existsSync(path) &&
    statSync(path).isDirectory() &&
    (existsSync(resolve(path, 'index.md')) || existsSync(resolve(path, 'README.md')))
  )
}

/**
 * The handbook links straight to source files (../../app/...), which the
 * docs site does not serve. Point those links at GitHub so they keep working.
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

      // CHANGELOG.md at repo root is included in docs/changelog.md and links to docs/...
      if (resolve(env.path) === resolve(docsRoot, 'changelog.md') && target.startsWith('docs/')) {
        const subTarget = target.slice('docs/'.length)
        if (isPublic && subTarget.startsWith('dev/')) {
          token.attrSet('href', `${repoUrl}/blob/${sourceBranch}/${target}${hash ? '#' + hash : ''}`)
          token.attrSet('target', '_blank')
          token.attrSet('rel', 'noreferrer')
          return defaultRender(tokens, idx, options, env, self)
        }

        const clean =
          '/' +
          subTarget
            .replace(/\/index\.md$/, '/')
            .replace(/\.md$/, '')
        token.attrSet('href', `${clean}${hash ? '#' + hash : ''}`)
        return defaultRender(tokens, idx, options, env, self)
      }

      const absolute = resolve(dirname(env.path), decodeURI(target))
      const inDocs = absolute === docsRoot || absolute.startsWith(docsRoot + sep)

      if (!inDocs || !(target.endsWith('.md') || target === '' || isDocsDir(absolute))) {
        const repoPath = relative(resolve(docsRoot, '..'), absolute).split(sep).join('/')
        token.attrSet('href', `${repoUrl}/blob/${sourceBranch}/${repoPath}${hash ? '#' + hash : ''}`)
        token.attrSet('target', '_blank')
        token.attrSet('rel', 'noreferrer')
      }
    }
    return defaultRender(tokens, idx, options, env, self)
  }
}

const arabicSidebar: DefaultTheme.Sidebar = [
  {
    text: 'البداية',
    items: [
      { text: 'مرحباً بك', link: '/start/' },
      { text: 'أول خطوات تشغيل المتجر', link: '/start/getting-started' },
      { text: 'قاموس المصطلحات', link: '/start/glossary' },
    ],
  },
  {
    text: 'دليل الاستخدام',
    items: [
      { text: 'إدارة الطلبات', link: '/guide/orders' },
      { text: 'العملاء والتقييمات', link: '/guide/customers' },
      { text: 'الإشعارات', link: '/guide/notifications' },
      { text: 'تجربة العميل على الموقع', link: '/guide/customer-journey' },
      { text: 'أسئلة شائعة وحل المشكلات', link: '/guide/faq' },
    ],
  },
  {
    text: 'الإدارة',
    items: [
      { text: 'الفئات والمنتجات', link: '/admin/catalog' },
      { text: 'التنويعات (الأحجام)', link: '/admin/variants' },
      { text: 'البيع بالوزن', link: '/admin/weight-options' },
      { text: 'باقات التوفير', link: '/admin/packages' },
      { text: 'الخيارات الإضافية', link: '/admin/extras' },
      { text: 'أقسام الواجهة', link: '/admin/sections' },
      { text: 'السلايدر وصفحات المحتوى', link: '/admin/content' },
      { text: 'الكوبونات', link: '/admin/coupons' },
      { text: 'الدفع الإلكتروني', link: '/admin/payments' },
      { text: 'الفروع والمحافظات والمناطق', link: '/admin/branches-delivery' },
      { text: 'إعدادات المتجر', link: '/admin/store-settings' },
      { text: 'سوق الإضافات', link: '/admin/plugins' },
      { text: 'ميتا (فيسبوك) والكتالوج', link: '/admin/meta' },
    ],
  },
  {
    text: 'التكامل',
    items: [
      { text: 'الربط مع نقطة البيع (POS)', link: '/integrations/pos' },
      { text: 'بوابات الدفع الإلكتروني', link: '/integrations/payment-gateways' },
      { text: 'ربط بكسل ميتا والكتالوج', link: '/integrations/meta' },
      { text: 'تسجيل الدخول بحساب جوجل', link: '/integrations/google' },
    ],
  },
]

const devSidebar: DefaultTheme.Sidebar = [
  {
    text: 'Developer',
    items: [
      { text: 'Handbook home', link: '/dev/' },
      { text: 'Product overview', link: '/dev/overview' },
      { text: 'Local setup', link: '/dev/setup' },
      { text: 'Architecture', link: '/dev/architecture' },
      { text: 'Plugin configuration pattern', link: '/dev/architecture/plugin-configuration-pattern' },
      { text: 'SaaS lifecycle engine', link: '/dev/architecture/saas-lifecycle-engine' },
      { text: 'Data model', link: '/dev/data-model' },
      { text: 'Interfaces (HTTP, events)', link: '/dev/interfaces' },
      { text: 'Configuration', link: '/dev/configuration' },
      { text: 'Security controls', link: '/dev/security' },
      { text: 'Development guide', link: '/dev/development' },
      { text: 'Themes & test IDs', link: '/dev/themes' },
      { text: 'Storefront performance', link: '/dev/storefront-performance' },
      { text: 'Connecting a POS (end to end)', link: '/dev/pos-website-link' },
      { text: 'Delivery monitor design', link: '/dev/order-pos-delivery-monitor' },
      { text: 'Documentation style guide', link: '/dev/style-guide' },
    ],
  },
  {
    text: 'Features',
    collapsed: true,
    items: [
      { text: 'Feature catalog', link: '/dev/features/' },
      { text: 'Store provisioning', link: '/dev/features/tenant-provisioning' },
      { text: 'Plugin marketplace', link: '/dev/features/plugin-marketplace' },
      { text: 'Store settings', link: '/dev/features/store-settings' },
      { text: 'Customer accounts', link: '/dev/features/customer-accounts' },
      { text: 'Catalog and products', link: '/dev/features/catalog' },
      { text: 'Combo packages', link: '/dev/features/combo-packages' },
      { text: 'Media optimizer', link: '/dev/features/media-optimizer' },
      { text: 'Cart', link: '/dev/features/cart' },
      { text: 'Checkout and orders', link: '/dev/features/checkout-and-orders' },
      { text: 'Delivery areas & branches', link: '/dev/features/locations-delivery' },
      { text: 'Guest checkout', link: '/dev/features/guest-checkout' },
      { text: 'Online payments', link: '/dev/features/payments' },
      { text: 'Coupons', link: '/dev/features/coupons' },
      { text: 'Favorites', link: '/dev/features/favorites' },
      { text: 'Product reviews', link: '/dev/features/product-reviews' },
      { text: 'Hero banners', link: '/dev/features/hero-banners' },
      { text: 'CMS pages', link: '/dev/features/cms-pages' },
      { text: 'Push notifications', link: '/dev/features/push-notifications' },
      { text: 'Meta marketing', link: '/dev/features/meta-marketing' },
      { text: 'POS integration', link: '/dev/features/pos-integration' },
      { text: 'Sample greeting', link: '/dev/features/sample-greeting' },
    ],
  },
  {
    text: 'Integrations',
    items: [
      { text: 'Google login', link: '/dev/integrations/google-login' },
      { text: 'Paymob gateway', link: '/dev/integrations/paymob' },
      { text: 'Kashier gateway', link: '/dev/integrations/kashier' },
      { text: 'Meta Pixel & CAPI', link: '/dev/integrations/meta-pixel' },
    ],
  },
  {
    text: 'Deployment and operations',
    items: [
      { text: 'Deployment', link: '/dev/ops/deployment' },
      { text: 'Production server & post-pull', link: '/dev/ops/production-server' },
      { text: 'Queue, workers & scheduler', link: '/dev/ops/queue-and-workers' },
      { text: 'Operations & troubleshooting', link: '/dev/ops/operations' },
      { text: 'Changelog', link: '/changelog' },
    ],
  },
]

const redirects: Record<string, string> = {
  // Old Arabic guide (/ar/...) -> new public Arabic sections
  '/ar': '/',
  '/ar/index': '/',
  '/ar/getting-started': '/start/getting-started',
  '/ar/orders': '/guide/orders',
  '/ar/customers': '/guide/customers',
  '/ar/notifications': '/guide/notifications',
  '/ar/customer-journey': '/guide/customer-journey',
  '/ar/faq': '/guide/faq',
  '/ar/catalog': '/admin/catalog',
  '/ar/variants': '/admin/variants',
  '/ar/weight-options': '/admin/weight-options',
  '/ar/packages': '/admin/packages',
  '/ar/extras': '/admin/extras',
  '/ar/sections': '/admin/sections',
  '/ar/content': '/admin/content',
  '/ar/coupons': '/admin/coupons',
  '/ar/payments': '/admin/payments',
  '/ar/branches-delivery': '/admin/branches-delivery',
  '/ar/store-settings': '/admin/store-settings',
  '/ar/plugins': '/admin/plugins',
  '/ar/meta': '/admin/meta',
  '/ar/pos': '/integrations/pos',

  // Old English developer handbook -> /dev/
  '/handbook': '/dev/',
  '/README': '/dev/',
  '/overview': '/dev/overview',
  '/getting-started': '/dev/setup',
  '/architecture': '/dev/architecture',
  '/architecture/plugin-configuration-pattern': '/dev/architecture/plugin-configuration-pattern',
  '/architecture/saas-lifecycle-engine': '/dev/architecture/saas-lifecycle-engine',
  '/data-model': '/dev/data-model',
  '/development': '/dev/development',
  '/interfaces': '/dev/interfaces',
  '/configuration': '/dev/configuration',
  '/security': '/dev/security',
  '/themes': '/dev/themes',
  '/storefront-performance': '/dev/storefront-performance',
  '/order-pos-delivery-monitor': '/dev/order-pos-delivery-monitor',
  '/pos-website-link': '/dev/pos-website-link',
  '/deployment': '/dev/ops/deployment',
  '/production-server': '/dev/ops/production-server',
  '/operations': '/dev/ops/operations',
  '/queue-and-workers': '/dev/ops/queue-and-workers',

  // Old English features -> /dev/features/
  '/features': '/dev/features/',
  '/features/index': '/dev/features/',
  '/features/README': '/dev/features/',
  '/features/cart': '/dev/features/cart',
  '/features/catalog': '/dev/features/catalog',
  '/features/checkout-and-orders': '/dev/features/checkout-and-orders',
  '/features/cms-pages': '/dev/features/cms-pages',
  '/features/combo-packages': '/dev/features/combo-packages',
  '/features/coupons': '/dev/features/coupons',
  '/features/customer-accounts': '/dev/features/customer-accounts',
  '/features/favorites': '/dev/features/favorites',
  '/features/guest-checkout': '/dev/features/guest-checkout',
  '/features/hero-banners': '/dev/features/hero-banners',
  '/features/locations-delivery': '/dev/features/locations-delivery',
  '/features/media-optimizer': '/dev/features/media-optimizer',
  '/features/meta-marketing': '/dev/features/meta-marketing',
  '/features/payments': '/dev/features/payments',
  '/features/plugin-marketplace': '/dev/features/plugin-marketplace',
  '/features/pos-integration': '/dev/features/pos-integration',
  '/features/product-reviews': '/dev/features/product-reviews',
  '/features/push-notifications': '/dev/features/push-notifications',
  '/features/sample-greeting': '/dev/features/sample-greeting',
  '/features/store-settings': '/dev/features/store-settings',
  '/features/tenant-provisioning': '/dev/features/tenant-provisioning',

  // Old English integrations -> /dev/integrations/
  '/integrations/google-login': '/dev/integrations/google-login',
  '/integrations/kashier': '/dev/integrations/kashier',
  '/integrations/meta-pixel': '/dev/integrations/meta-pixel',
  '/integrations/paymob': '/dev/integrations/paymob',
}

const rootNav: DefaultTheme.NavItem[] = [
  { text: 'البداية', link: '/start/', activeMatch: '^/start/' },
  { text: 'دليل الاستخدام', link: '/guide/orders', activeMatch: '^/guide/' },
  { text: 'الإدارة', link: '/admin/catalog', activeMatch: '^/admin/' },
  { text: 'التكامل', link: '/integrations/pos', activeMatch: '^/integrations/' },
  ...(isPublic ? [] : [{ text: 'Developer', link: '/dev/', activeMatch: '^/dev/' }]),
  productSwitcher('website', productUrls),
]

export default withMermaid(
  defineConfig({
    title: 'Turbo Tenant',
    description: 'دليل استخدام متجر Turbo Tenant للطلب أونلاين ووثائق المطورين',
    cleanUrls: true,
    lastUpdated: true,
    srcExclude: isPublic ? ['dev/**'] : [],
    head: [
      ['link', { rel: 'icon', type: 'image/svg+xml', href: '/logo.svg' }],
      ['style', {}, ':root { --brand: #c2410c; }'],
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
    themeConfig: {
      logo: '/logo.svg',
      isPublic,
      redirects,
      search: {
        provider: 'local',
        options: {
          locales: {
            root: {
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
    } as DefaultTheme.Config & { isPublic: boolean; redirects: Record<string, string> },
    locales: {
      root: {
        label: 'العربية',
        lang: 'ar',
        dir: 'rtl',
        themeConfig: {
          nav: rootNav,
          sidebar: arabicSidebar,
          outline: { level: [2, 3], label: 'في هذه الصفحة' },
          docFooter: { prev: 'السابق', next: 'التالي' },
          lastUpdated: { text: 'آخر تحديث' },
          darkModeSwitchLabel: 'المظهر',
          lightModeSwitchTitle: 'الوضع الفاتح',
          darkModeSwitchTitle: 'الوضع الداكن',
          sidebarMenuLabel: 'القائمة',
          returnToTopLabel: 'العودة للأعلى',
          langMenuLabel: 'اللغة',
          notFound: { title: 'الصفحة غير موجودة', linkText: 'العودة للرئيسية', quote: '' },
          footer: { message: 'دليل استخدام متجر Turbo Tenant' },
        },
      },
      ...(isPublic
        ? {}
        : {
            dev: {
              label: 'Developer (English)',
              lang: 'en',
              dir: 'ltr',
              link: '/dev/',
              themeConfig: {
                nav: [
                  { text: 'Handbook', link: '/dev/', activeMatch: '^/dev/(?!ops/|features/|integrations/)' },
                  { text: 'Features', link: '/dev/features/', activeMatch: '^/dev/features/' },
                  { text: 'Operations', link: '/dev/ops/deployment', activeMatch: '^/dev/ops/' },
                  { text: 'Style guide', link: '/dev/style-guide' },
                  { text: 'دليل المستخدم', link: '/' },
                  productSwitcher('website', productUrls),
                ],
                sidebar: { '/dev/': devSidebar },
                outline: { level: [2, 3], label: 'On this page' },
                docFooter: { prev: 'Previous', next: 'Next' },
                lastUpdated: { text: 'Last updated' },
                footer: {
                  message: 'Turbo Tenant developer handbook. End-user Arabic guide lives at /.',
                },
              },
            },
          }),
    },
    mermaid: {
      theme: 'base',
      themeVariables: {
        primaryColor: '#ffedd5',
        primaryBorderColor: '#c2410c',
        primaryTextColor: '#7c2d12',
        lineColor: '#ea580c',
        secondaryColor: '#fef3c7',
        tertiaryColor: '#f8fafc',
        clusterBkg: '#fff7ed',
        clusterBorder: '#fed7aa',
        fontFamily: 'Inter, IBM Plex Sans Arabic, sans-serif',
      },
    },
    vite: {
      // Inline PostCSS config so Vite does not load the application's postcss.config.js (Tailwind) from the repo root.
      css: { postcss: {} },
      build: { chunkSizeWarningLimit: 4000 }, // mermaid is large
    },
  }),
)
