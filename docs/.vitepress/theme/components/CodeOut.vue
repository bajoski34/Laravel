<script setup lang="ts">
import { ref } from 'vue'

const props = defineProps<{ code: string }>()
const copied = ref(false)

async function copy() {
  try {
    await navigator.clipboard.writeText(props.code)
    copied.value = true
    setTimeout(() => (copied.value = false), 1500)
  } catch {
    copied.value = false
  }
}
</script>

<template>
  <div class="tool__out">
    <pre><code>{{ code }}</code></pre>
    <button type="button" class="tool__copy" @click="copy">{{ copied ? 'Copied' : 'Copy' }}</button>
  </div>
</template>
