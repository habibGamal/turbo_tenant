---
layout: home
title: Turbo Tenant
description: نظام متاجر الطلب أونلاين للمطاعم والمحلات مع لوحة إدارة عربية، وسوق إضافات، وربط مع نقطة البيع.

hero:
  name: Turbo Tenant
  text: متجرك الإلكتروني للطلب أونلاين من مكان واحد
  tagline: من إعداد المنيو والفروع ومناطق التوصيل، إلى استقبال الطلبات وإرسالها لنقطة البيع في الفرع، والدفع الإلكتروني والكوبونات والتسويق.
  image:
    src: /logo.svg
    alt: Turbo Tenant
  actions:
    - theme: brand
      text: ابدأ من هنا
      link: /start/
    - theme: alt
      text: دليل الاستخدام
      link: /guide/orders
    - theme: alt
      text: الإدارة
      link: /admin/catalog
---

<script setup>
import { computed } from 'vue'
import { useData } from 'vitepress'
import { VPFeatures } from 'vitepress/theme'

const { theme } = useData()

const allSections = [
  {
    title: 'البداية',
    details: 'نظرة عامة على ما يقدمه المتجر، أول خطوات التشغيل، وقاموس المصطلحات.',
    link: '/start/',
  },
  {
    title: 'دليل الاستخدام',
    details: 'العمل اليومي في المتجر: متابعة الطلبات، العملاء والتقييمات، الإشعارات، تجربة العميل، والأسئلة الشائعة.',
    link: '/guide/orders',
  },
  {
    title: 'الإدارة',
    details: 'إعداد الفئات والمنتجات والأحجام والإضافات والأوزان والباقات والفروع والتوصيل والكوبونات والإعدادات.',
    link: '/admin/catalog',
  },
  {
    title: 'التكامل',
    details: 'ربط نقطة البيع (POS)، بوابات الدفع الإلكتروني، بكسل ميتا والكتالوج، وتسجيل الدخول بحساب جوجل.',
    link: '/integrations/pos',
  },
  {
    title: 'Developer',
    details: 'Architecture, multi-tenancy, plugin kernel, feature catalog, API contracts, themes, and testing.',
    link: '/dev/',
    privateOnly: true,
  },
  {
    title: 'Deployment and operations',
    details: 'Deployment checklist, production nginx server, queue workers, scheduler, and troubleshooting.',
    link: '/dev/ops/deployment',
    privateOnly: true,
  },
]

const visibleSections = computed(() =>
  allSections.filter((section) => !theme.value.isPublic || !section.privateOnly),
)
</script>

<VPFeatures :features="visibleSections" />

<div v-if="!theme.isPublic" style="margin-top: 2rem; text-align: center;">
  <p>للمطورين ومسؤولي الخادم: انتقل إلى <a href="/dev/">وثائق المطورين (Developer handbook)</a>.</p>
</div>
