<script setup lang="ts">
// The default layout, plus the "Copy page" menu on every docs page:
// above "On this page" on wide screens, and at the top of the page where that column is hidden.
import DefaultTheme from 'vitepress/theme-without-fonts'
import CopyOrDownloadAsMarkdownButtons from 'vitepress-plugin-llms/vitepress-components/CopyOrDownloadAsMarkdownButtons.vue'

const { Layout } = DefaultTheme
</script>

<template>
  <Layout>
    <template #aside-outline-before>
      <div class="page-actions page-actions--aside">
        <CopyOrDownloadAsMarkdownButtons />
      </div>
    </template>
    <template #doc-before>
      <div class="page-actions page-actions--top">
        <CopyOrDownloadAsMarkdownButtons />
      </div>
    </template>
  </Layout>
</template>
