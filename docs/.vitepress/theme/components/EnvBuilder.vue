<script setup lang="ts">
import { computed, reactive } from 'vue'
import CodeOut from './CodeOut.vue'

const currencies = ['NGN', 'GHS', 'KES', 'UGX', 'TZS', 'ZAR', 'RWF', 'XAF', 'XOF', 'ZMW', 'USD', 'EUR', 'GBP']
const countries = ['NG', 'GH', 'KE', 'UG', 'TZ', 'ZA', 'RW', 'CM', 'CI', 'ZM', 'US', 'GB']

const form = reactive({
  env: 'staging',
  businessName: '',
  country: 'NG',
  currency: 'NGN',
  prefix: 'LARAVEL-',
  checksum: false,
  webhookPath: 'flutterwave/webhook',
  queue: '',
  dedupeStore: '',
  notifyChargebacks: '',
  notifyTransfers: '',
})

const quote = (v: string) => (/[\s#"']/.test(v) ? `"${v.replace(/"/g, '\\"')}"` : v)

const env = computed(() => {
  const live = form.env === 'live'
  const lines = [
    '# Keys from the Flutterwave dashboard: Settings > API keys',
    `FLW_PUBLIC_KEY=${live ? 'FLWPUBK-xxxxxxxx-X' : 'FLWPUBK_TEST-xxxxxxxx-X'}`,
    `FLW_SECRET_KEY=${live ? 'FLWSECK-xxxxxxxx-X' : 'FLWSECK_TEST-xxxxxxxx-X'}`,
    `FLW_ENCRYPTION_KEY=${live ? 'xxxxxxxxxxxx' : 'FLWSECK_TESTxxxxxxxx'}`,
    '# Settings > Webhooks: the same value you type into "Secret hash"',
    'FLW_SECRET_HASH=choose-a-long-random-string',
    `FLW_ENVIRONMENT=${form.env}`,
    '',
  ]

  if (form.businessName) lines.push(`FLW_BUSINESS_NAME=${quote(form.businessName)}`)
  if (form.country !== 'NG') lines.push(`FLW_DEFAULT_COUNTRY=${form.country}`)
  if (form.currency !== 'NGN') lines.push(`FLW_DEFAULT_CURRENCY=${form.currency}`)
  if (form.prefix && form.prefix !== 'LARAVEL-') lines.push(`FLW_TRANSACTION_PREFIX=${quote(form.prefix)}`)
  if (form.checksum) lines.push('FLW_CHECKSUM=true')
  if (form.webhookPath && form.webhookPath !== 'flutterwave/webhook') lines.push(`FLW_WEBHOOK_PATH=${form.webhookPath.replace(/^\/+/, '')}`)
  if (form.queue) lines.push(`FLW_WEBHOOK_QUEUE=${form.queue}`)
  if (form.dedupeStore) lines.push(`FLW_WEBHOOK_DEDUPE_STORE=${form.dedupeStore}`)
  if (form.notifyChargebacks) lines.push(`FLW_NOTIFY_CHARGEBACKS=${quote(form.notifyChargebacks)}`)
  if (form.notifyTransfers) lines.push(`FLW_NOTIFY_FAILED_TRANSFERS=${quote(form.notifyTransfers)}`)

  return lines.join('\n').replace(/\n+$/, '')
})

const webhookUrl = computed(() => `https://your-app.com/${form.webhookPath.replace(/^\/+/, '') || 'flutterwave/webhook'}`)
</script>

<template>
  <div class="tool">
    <p class="tool__title">.env builder</p>
    <p class="tool__hint">Pick your settings and copy the block into <code>.env</code>. Nothing you type leaves this page. Defaults are left out so your file stays short.</p>

    <div class="tool__grid">
      <label>Environment
        <select v-model="form.env">
          <option value="staging">staging (test keys)</option>
          <option value="live">live</option>
        </select>
      </label>
      <label>Business name
        <input v-model="form.businessName" type="text" placeholder="Flutterwave Store" />
      </label>
      <label>Default country
        <select v-model="form.country"><option v-for="c in countries" :key="c">{{ c }}</option></select>
      </label>
      <label>Default currency
        <select v-model="form.currency"><option v-for="c in currencies" :key="c">{{ c }}</option></select>
      </label>
      <label>Transaction prefix
        <input v-model="form.prefix" type="text" />
      </label>
      <label>Webhook path
        <input v-model="form.webhookPath" type="text" />
        <small>Dashboard URL: {{ webhookUrl }}</small>
      </label>
      <label>Webhook queue
        <input v-model="form.queue" type="text" placeholder="empty = handle in the request" />
      </label>
      <label>Dedupe cache store
        <input v-model="form.dedupeStore" type="text" placeholder="redis, database..." />
      </label>
      <label>Email on chargeback
        <input v-model="form.notifyChargebacks" type="text" placeholder="ops@your-app.com" />
      </label>
      <label>Email on failed payout
        <input v-model="form.notifyTransfers" type="text" placeholder="finance@your-app.com" />
      </label>
      <label class="tool__check">
        <input v-model="form.checksum" type="checkbox" /> Checksum on checkouts
      </label>
    </div>

    <CodeOut :code="env" />
  </div>
</template>
