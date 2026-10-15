<script setup lang="ts">
import { computed, ref } from 'vue'
import services from '../../data/services.json'

type Method = { name: string; static: boolean; params: string; returns: string; summary: string; example: string }
type Service = { key: string; class: string; accessor: string | null; summary: string; methods: Method[] }

const all = services as Service[]
const query = ref('')
const active = ref<string>('all')

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  return all
    .filter((s) => active.value === 'all' || s.key === active.value)
    .map((s) => ({
      ...s,
      methods: q
        ? s.methods.filter((m) => `${s.key} ${s.class} ${m.name} ${m.summary} ${m.params}`.toLowerCase().includes(q))
        : s.methods,
    }))
    .filter((s) => s.methods.length > 0)
})

const count = computed(() => filtered.value.reduce((n, s) => n + s.methods.length, 0))

function call(s: Service, m: Method) {
  if (m.static) return `${s.class}::${m.name}(${m.params})`
  const target = s.accessor ? `Flutterwave::${s.accessor}()` : `Flutterwave::use('${s.key}')`
  return `${target}->${m.name}(${m.params})`
}
</script>

<template>
  <div class="tool explorer">
    <p class="tool__title">Service explorer</p>
    <p class="tool__hint">
      Every service and public method in the package, read from <code>src/Services</code> when the docs are built.
      Call any of them through the facade, e.g. <code>Flutterwave::transfers()->create([...])</code>.
    </p>

    <div class="tool__grid">
      <label>Search
        <input v-model="query" type="search" placeholder="refund, bvn, bulk, resolveAccount..." />
      </label>
      <label>Service
        <select v-model="active">
          <option value="all">All {{ all.length }} services</option>
          <option v-for="s in all" :key="s.key" :value="s.key">{{ s.accessor ?? s.key }}() · {{ s.class }}</option>
        </select>
      </label>
    </div>
    <p class="tool__hint" style="margin: 12px 0 0">{{ count }} method{{ count === 1 ? '' : 's' }}</p>

    <section v-for="s in filtered" :key="s.key" class="explorer__service">
      <h3 :id="`svc-${s.key}`">
        <code>{{ s.accessor ? `Flutterwave::${s.accessor}()` : `Flutterwave::use('${s.key}')` }}</code>
        <span class="explorer__class">{{ s.class }}</span>
      </h3>
      <p v-if="s.summary" class="explorer__summary">{{ s.summary }}</p>

      <details v-for="m in s.methods" :key="m.name" class="explorer__method" :open="!!query">
        <summary>
          <code>{{ m.name }}</code>
          <span class="explorer__ret" v-if="m.returns">: {{ m.returns }}</span>
          <span class="explorer__desc" v-if="m.summary">{{ m.summary }}</span>
        </summary>
        <pre><code>{{ call(s, m) }}</code></pre>
        <pre v-if="m.example"><code>{{ m.example }}</code></pre>
      </details>
    </section>

    <p v-if="!filtered.length" class="tool__status tool__status--idle">Nothing matches "{{ query }}".</p>
  </div>
</template>

<style scoped>
.explorer__service {
  margin-top: 24px;
}
.explorer__service h3 {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 8px;
  margin: 0 0 4px;
  padding: 0;
  border: 0;
  font-size: 16px;
}
.explorer__class {
  font-size: 12px;
  font-weight: 400;
  color: var(--vp-c-text-3);
}
.explorer__summary {
  margin: 0 0 8px;
  font-size: 14px;
  color: var(--vp-c-text-2);
}
.explorer__method {
  border-top: 1px solid var(--vp-c-divider);
  padding: 6px 0;
}
.explorer__method summary {
  cursor: pointer;
  font-size: 14px;
  line-height: 1.6;
}
.explorer__ret {
  font-family: var(--vp-font-family-mono);
  font-size: 12px;
  color: var(--vp-c-text-3);
}
.explorer__desc {
  display: block;
  margin-left: 16px;
  font-size: 13px;
  color: var(--vp-c-text-2);
}
.explorer__method pre {
  margin: 8px 0 4px;
  padding: 10px 12px;
  border-radius: 6px;
  background: var(--vp-code-block-bg);
  overflow-x: auto;
  font-size: 12.5px;
}
</style>
