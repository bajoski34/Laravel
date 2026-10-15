<script setup lang="ts">
import { computed, ref } from 'vue'
import services from '../../data/services.json'
import CodeOut from './CodeOut.vue'

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
    <div class="tool__head">
      <p class="tool__title">Service explorer</p>
      <p class="tool__hint">
        Every service and public method in the package, read from <code>src/Services</code> when the docs are built.
        Call any of them through the facade, for example <code>Flutterwave::transfers()->create([...])</code>.
      </p>
    </div>

    <div class="tool__body">
      <div class="tool__fields">
        <label class="tool__field">
          <span class="tool__label">Search</span>
          <input v-model="query" type="search" placeholder="refund, bvn, bulk, resolveAccount" />
        </label>
        <label class="tool__field">
          <span class="tool__label">Service</span>
          <select v-model="active">
            <option value="all">All {{ all.length }} services</option>
            <option v-for="s in all" :key="s.key" :value="s.key">{{ s.accessor ?? s.key }}() · {{ s.class }}</option>
          </select>
        </label>
      </div>
      <p class="tool__note" aria-live="polite">{{ count }} method{{ count === 1 ? '' : 's' }}</p>
    </div>

    <div class="tool__out explorer__list">
      <section v-for="s in filtered" :key="s.key" class="explorer__service">
        <h3 :id="`svc-${s.key}`">
          <code>{{ s.accessor ? `Flutterwave::${s.accessor}()` : `Flutterwave::use('${s.key}')` }}</code>
          <span class="explorer__class">{{ s.class }}</span>
        </h3>
        <p v-if="s.summary" class="explorer__summary">{{ s.summary }}</p>

        <details v-for="m in s.methods" :key="m.name" class="explorer__method" :open="!!query">
          <summary>
            <code>{{ m.name }}</code>
            <span v-if="m.returns" class="explorer__ret">: {{ m.returns }}</span>
            <span v-if="m.summary" class="explorer__desc">{{ m.summary }}</span>
          </summary>
          <div class="explorer__code">
            <CodeOut :code="call(s, m)" lang="php" />
            <CodeOut v-if="m.example" :code="m.example" lang="php" />
          </div>
        </details>
      </section>

      <p v-if="!filtered.length" class="tool__status tool__status--idle">Nothing matches "{{ query }}".</p>
    </div>
  </div>
</template>

<style scoped>
.explorer__list {
  gap: 12px;
}
.explorer__service {
  padding: 14px 16px 6px;
  border: 1px solid var(--vp-c-divider);
  border-radius: 10px;
  background: var(--vp-c-bg);
}
.vp-doc .explorer__service h3 {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 8px;
  margin: 0 0 4px;
  padding: 0;
  border: 0;
  font-size: 15px;
}
.explorer__class {
  font-size: 12px;
  font-weight: 400;
  color: var(--vp-c-text-3);
}
.vp-doc .explorer__summary {
  margin: 0 0 8px;
  font-size: 14px;
  line-height: 1.55;
  color: var(--vp-c-text-2);
}
.explorer__method {
  border-top: 1px solid var(--vp-c-divider);
  padding: 8px 0;
}
.explorer__method summary {
  cursor: pointer;
  font-size: 14px;
  line-height: 1.6;
}
.explorer__method summary:hover code {
  color: var(--vp-c-brand-1);
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
  line-height: 1.55;
  color: var(--vp-c-text-2);
}
.explorer__code {
  display: grid;
  gap: 8px;
  margin: 8px 0 4px;
}
</style>
