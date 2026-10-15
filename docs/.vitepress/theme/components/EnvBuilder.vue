<script setup lang="ts">
import { computed, reactive } from 'vue'
import CodeOut from './CodeOut.vue'

const currencies = ['NGN', 'GHS', 'KES', 'UGX', 'TZS', 'ZAR', 'RWF', 'XAF', 'XOF', 'ZMW', 'USD', 'EUR', 'GBP']
const countries = ['NG', 'GH', 'KE', 'UG', 'TZ', 'ZA', 'RW', 'CM', 'CI', 'ZM', 'US', 'GB']

const form = reactive({
  env: 'staging' as 'staging' | 'live',
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
const path = computed(() => form.webhookPath.replace(/^\/+/, '') || 'flutterwave/webhook')

const env = computed(() => {
  const live = form.env === 'live'
  const lines = [
    '# Settings > API keys on the Flutterwave dashboard',
    `FLW_PUBLIC_KEY=${live ? 'FLWPUBK-xxxxxxxx-X' : 'FLWPUBK_TEST-xxxxxxxx-X'}`,
    `FLW_SECRET_KEY=${live ? 'FLWSECK-xxxxxxxx-X' : 'FLWSECK_TEST-xxxxxxxx-X'}`,
    `FLW_ENCRYPTION_KEY=${live ? 'xxxxxxxxxxxx' : 'FLWSECK_TESTxxxxxxxx'}`,
    '',
    '# The same value you type into Settings > Webhooks > Secret hash',
    'FLW_SECRET_HASH=choose-a-long-random-string',
    `FLW_ENVIRONMENT=${form.env}`,
  ]

  const extra: string[] = []
  if (form.businessName) extra.push(`FLW_BUSINESS_NAME=${quote(form.businessName)}`)
  if (form.country !== 'NG') extra.push(`FLW_DEFAULT_COUNTRY=${form.country}`)
  if (form.currency !== 'NGN') extra.push(`FLW_DEFAULT_CURRENCY=${form.currency}`)
  if (form.prefix && form.prefix !== 'LARAVEL-') extra.push(`FLW_TRANSACTION_PREFIX=${quote(form.prefix)}`)
  if (form.checksum) extra.push('FLW_CHECKSUM=true')
  if (path.value !== 'flutterwave/webhook') extra.push(`FLW_WEBHOOK_PATH=${path.value}`)
  if (form.queue) extra.push(`FLW_WEBHOOK_QUEUE=${form.queue}`)
  if (form.dedupeStore) extra.push(`FLW_WEBHOOK_DEDUPE_STORE=${form.dedupeStore}`)
  if (form.notifyChargebacks) extra.push(`FLW_NOTIFY_CHARGEBACKS=${quote(form.notifyChargebacks)}`)
  if (form.notifyTransfers) extra.push(`FLW_NOTIFY_FAILED_TRANSFERS=${quote(form.notifyTransfers)}`)

  return [...lines, ...(extra.length ? ['', ...extra] : [])].join('\n')
})
</script>

<template>
  <div class="tool">
    <div class="tool__head">
      <p class="tool__title">.env builder</p>
      <p class="tool__hint">Pick your settings and copy the block into <code>.env</code>. Nothing you type leaves this page, and defaults are left out so your file stays short.</p>
    </div>

    <div class="tool__body">
      <div class="tool__row">
        <span class="tool__label" id="env-mode">Keys</span>
        <div class="tool__seg" role="group" aria-labelledby="env-mode">
          <button type="button" :aria-pressed="form.env === 'staging'" @click="form.env = 'staging'">Test</button>
          <button type="button" :aria-pressed="form.env === 'live'" @click="form.env = 'live'">Live</button>
        </div>
      </div>

      <div class="tool__fields">
        <label class="tool__field">
          <span class="tool__label">Business name</span>
          <input v-model="form.businessName" type="text" placeholder="Flutterwave Store" />
        </label>
        <label class="tool__field">
          <span class="tool__label">Default country</span>
          <select v-model="form.country"><option v-for="c in countries" :key="c">{{ c }}</option></select>
        </label>
        <label class="tool__field">
          <span class="tool__label">Default currency</span>
          <select v-model="form.currency"><option v-for="c in currencies" :key="c">{{ c }}</option></select>
        </label>
        <label class="tool__field">
          <span class="tool__label">Transaction prefix</span>
          <input v-model="form.prefix" type="text" />
        </label>
        <label class="tool__field">
          <span class="tool__label">Webhook path</span>
          <input v-model="form.webhookPath" type="text" />
          <span class="tool__note">Dashboard URL: https://your-app.com/{{ path }}</span>
        </label>
        <label class="tool__field">
          <span class="tool__label">Webhook queue</span>
          <input v-model="form.queue" type="text" placeholder="Empty handles them in the request" />
        </label>
        <label class="tool__field">
          <span class="tool__label">Dedupe cache store</span>
          <input v-model="form.dedupeStore" type="text" placeholder="redis or database" />
        </label>
        <label class="tool__field">
          <span class="tool__label">Email on chargeback</span>
          <input v-model="form.notifyChargebacks" type="text" placeholder="ops@your-app.com" />
        </label>
        <label class="tool__field">
          <span class="tool__label">Email on failed payout</span>
          <input v-model="form.notifyTransfers" type="text" placeholder="finance@your-app.com" />
        </label>
      </div>

      <div class="tool__chips">
        <label class="tool__chip"><input v-model="form.checksum" type="checkbox" /> Checksum on every checkout</label>
      </div>
    </div>

    <div class="tool__out">
      <CodeOut :code="env" lang="dotenv" title=".env" />
    </div>
  </div>
</template>
