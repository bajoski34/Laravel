<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { escapeHtml, highlight, type CodeLang } from '../highlight'

const props = withDefaults(defineProps<{ code: string; lang?: CodeLang; title?: string }>(), {
  lang: 'php',
})

const labels: Record<CodeLang, string> = {
  php: 'php',
  shellscript: 'bash',
  dotenv: 'env',
  tsx: 'tsx',
  vue: 'vue',
  svelte: 'svelte',
}

// Plain text first, so the code is readable before (or without) the highlighter.
const plain = computed(
  () =>
    `<pre class="shiki vp-code"><code>${props.code
      .split('\n')
      .map((l) => `<span class="line">${escapeHtml(l)}</span>`)
      .join('\n')}</code></pre>`,
)
const highlighted = ref('')
let request = 0

async function render() {
  const id = ++request
  try {
    const html = await highlight(props.code, props.lang)
    if (id === request) highlighted.value = html
  } catch {
    if (id === request) highlighted.value = ''
  }
}

onMounted(render)
watch(() => [props.code, props.lang], render)

// The same structure VitePress renders for Markdown code blocks, so its copy button
// handler, language label and light/dark styles apply as they do everywhere else.
const inner = computed(
  () =>
    `<button title="Copy Code" class="copy"></button><span class="lang">${labels[props.lang]}</span>` +
    (highlighted.value || plain.value),
)
</script>

<template>
  <div class="tool-code" :class="{ 'tool-code--titled': title }">
    <div v-if="title" class="tool-code__title">{{ title }}</div>
    <div :class="[`language-${lang}`, 'vp-adaptive-theme']" v-html="inner" />
  </div>
</template>
