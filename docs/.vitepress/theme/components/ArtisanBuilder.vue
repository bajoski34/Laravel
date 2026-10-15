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
    <div class="tool__head">
      <p class="tool__title">Artisan command builder</p>
      <p class="tool__hint">Pick a command, fill in what you need and copy the line. Arguments and options are read from the package source when the docs are built.</p>
    </div>

    <div class="tool__body">
      <label class="tool__field">
        <span class="tool__label">Command</span>
        <select v-model="selected">
          <option v-for="c in list" :key="c.name" :value="c.name">{{ c.name }}</option>
        </select>
        <span class="tool__note">{{ command.description }}.</span>
      </label>

      <div v-if="command.args.length || command.options.some((o) => o.takesValue)" class="tool__fields">
        <label v-for="a in command.args" :key="'a' + a.name" class="tool__field">
          <span class="tool__label">{{ a.name }}<span v-if="!a.optional" aria-hidden="true"> *</span></span>
          <input v-model="values[`arg:${a.name}`]" type="text" :placeholder="a.default || (a.optional ? 'Optional' : 'Required')" />
          <span class="tool__note">{{ a.help }}</span>
        </label>
        <label v-for="o in command.options.filter((o) => o.takesValue)" :key="'o' + o.name" class="tool__field">
          <span class="tool__label">--{{ o.name }}</span>
          <input v-model="values[`opt:${o.name}`]" type="text" :placeholder="o.default ? `Default ${o.default}` : 'Optional'" />
          <span class="tool__note">{{ o.help }}</span>
        </label>
      </div>

      <div v-if="command.options.some((o) => !o.takesValue)" class="tool__row">
        <span class="tool__label">Flags</span>
        <div class="tool__chips">
          <label v-for="o in command.options.filter((o) => !o.takesValue)" :key="'f' + o.name" class="tool__chip" :title="o.help">
            <input v-model="flags[o.name]" type="checkbox" /> --{{ o.name }}
          </label>
        </div>
      </div>
    </div>

    <div class="tool__out">
      <p v-if="missing.length" class="tool__status tool__status--idle">Still needed: {{ missing.join(', ') }}</p>
      <CodeOut :code="line" lang="shellscript" title="Terminal" />
    </div>
  </div>
</template>
