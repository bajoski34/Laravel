<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import CodeOut from './CodeOut.vue'
import commands from '../../data/artisan.json'

type Arg = { name: string; help: string; optional: boolean; default: string }
type Opt = { name: string; help: string; takesValue: boolean; default: string }
type Command = { name: string; description: string; args: Arg[]; options: Opt[] }

const list = commands as Command[]
const selected = ref(list.find((c) => c.name === 'flutterwave:doctor')?.name ?? list[0].name)
const command = computed(() => list.find((c) => c.name === selected.value)!)

const values = reactive<Record<string, string>>({})
const flags = reactive<Record<string, boolean>>({})

watch(
  command,
  (c) => {
    for (const k of Object.keys(values)) delete values[k]
    for (const k of Object.keys(flags)) delete flags[k]
    c.args.forEach((a) => (values[`arg:${a.name}`] = ''))
    c.options.forEach((o) => (o.takesValue ? (values[`opt:${o.name}`] = '') : (flags[o.name] = false)))
  },
  { immediate: true },
)

const quote = (v: string) => (/^[\w@.:/,+-]+$/.test(v) ? v : `"${v.replace(/"/g, '\\"')}"`)

const missing = computed(() => command.value.args.filter((a) => !a.optional && !values[`arg:${a.name}`]?.trim()).map((a) => a.name))

const line = computed(() => {
  const parts = ['php artisan', command.value.name]
  for (const a of command.value.args) {
    const v = values[`arg:${a.name}`]?.trim()
    if (v) parts.push(quote(v))
    else if (!a.optional) parts.push(`<${a.name}>`)
  }
  for (const o of command.value.options) {
    if (o.takesValue) {
      const v = values[`opt:${o.name}`]?.trim()
      if (v) parts.push(`--${o.name}=${quote(v)}`)
    } else if (flags[o.name]) {
      parts.push(`--${o.name}`)
    }
  }
  return parts.join(' ')
})
</script>

<template>
  <div class="tool">
    <p class="tool__title">Artisan command builder</p>
    <p class="tool__hint">Pick a command, fill in what you need, copy the line. Arguments and options are read from the package source.</p>

    <label>Command
      <select v-model="selected">
        <option v-for="c in list" :key="c.name" :value="c.name">{{ c.name }}  ·  {{ c.description }}</option>
      </select>
    </label>
    <p class="tool__hint" style="margin: 8px 0 16px">{{ command.description }}.</p>

    <div class="tool__grid">
      <label v-for="a in command.args" :key="'a' + a.name">
        {{ a.name }}<span v-if="!a.optional"> *</span>
        <input v-model="values[`arg:${a.name}`]" type="text" :placeholder="a.default || (a.optional ? 'optional' : '')" />
        <small>{{ a.help }}</small>
      </label>
      <label v-for="o in command.options.filter((o) => o.takesValue)" :key="'o' + o.name">
        --{{ o.name }}
        <input v-model="values[`opt:${o.name}`]" type="text" :placeholder="o.default ? `default ${o.default}` : ''" />
        <small>{{ o.help }}</small>
      </label>
      <label v-for="o in command.options.filter((o) => !o.takesValue)" :key="'f' + o.name" class="tool__check" :title="o.help">
        <input v-model="flags[o.name]" type="checkbox" /> --{{ o.name }} <small>{{ o.help }}</small>
      </label>
    </div>

    <CodeOut :code="line" />
    <p v-if="missing.length" class="tool__status tool__status--idle">Needs: {{ missing.join(', ') }}</p>
  </div>
</template>
