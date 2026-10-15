<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import CodeOut from './CodeOut.vue'

const sample = JSON.stringify(
  {
    event: 'charge.completed',
    data: {
      id: 4975363,
      tx_ref: 'LARAVEL-aB3xQ1729000000',
      flw_ref: 'FLW-MOCK-0f1c2d3e4f',
      amount: 5000,
      currency: 'NGN',
      charged_amount: 5000,
      status: 'successful',
      payment_type: 'card',
      customer: { id: 2280112, email: 'customer@example.com' },
    },
  },
  null,
  2,
)

const secret = ref('choose-a-long-random-string')
const body = ref(sample)
const received = ref('')
const url = ref('http://localhost:8000/flutterwave/webhook')
const signature = ref('')
const error = ref('')
const supported = ref(true)

async function hmacBase64(key: string, message: string): Promise<string> {
  const enc = new TextEncoder()
  const cryptoKey = await crypto.subtle.importKey('raw', enc.encode(key), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign'])
  const sig = new Uint8Array(await crypto.subtle.sign('HMAC', cryptoKey, enc.encode(message)))
  let binary = ''
  sig.forEach((b) => (binary += String.fromCharCode(b)))
  return btoa(binary)
}

async function refresh() {
  error.value = ''
  if (!supported.value) return
  if (!secret.value) {
    signature.value = ''
    return
  }
  try {
    signature.value = await hmacBase64(secret.value, body.value)
  } catch (e) {
    error.value = (e as Error).message
  }
}

onMounted(() => {
  supported.value = typeof crypto !== 'undefined' && !!crypto.subtle
  refresh()
})
watch([secret, body], refresh)

const verdict = computed(() => {
  const got = received.value.trim()
  if (!got || !signature.value) return null
  // Mirrors Webhooks::isValid(): an HMAC in flutterwave-signature, or the raw hash in verif-hash.
  if (got === signature.value) return { ok: true, text: 'Valid flutterwave-signature: the body and secret hash match.' }
  if (got === secret.value) return { ok: true, text: 'Valid verif-hash (legacy header): it equals your secret hash.' }
  return { ok: false, text: 'Does not match. Check FLW_SECRET_HASH, and that you signed the raw body, byte for byte, not re-encoded JSON.' }
})

const bodyValid = computed(() => {
  try {
    JSON.parse(body.value)
    return true
  } catch {
    return false
  }
})

const shellQuote = (s: string) => `'${s.replace(/'/g, `'\\''`)}'`

const curl = computed(() =>
  [
    `curl -X POST ${shellQuote(url.value)} \\`,
    `  -H 'Content-Type: application/json' \\`,
    `  -H 'flutterwave-signature: ${signature.value}' \\`,
    `  --data-raw ${shellQuote(body.value)}`,
  ].join('\n'),
)

const php = computed(
  () => `// Feature test: post a signed webhook to your app.
use Flutterwave\\Payments\\Facades\\Flutterwave;

$body = json_encode($payload);

$this->call('POST', '/flutterwave/webhook', [], [], [], [
    'HTTP_FLUTTERWAVE_SIGNATURE' => base64_encode(hash_hmac('sha256', $body, config('flutterwave.secretHash'), true)),
    'CONTENT_TYPE' => 'application/json',
], $body)->assertOk();

// Or with the fake, which also answers the charge re-verification:
// Flutterwave::fake()->transaction($payload['data']['id'], ['amount' => 5000]);
// $this->postJson('/flutterwave/webhook', $payload, FlutterwaveFake::webhookHeaders())->assertOk();`,
)
</script>

<template>
  <div class="tool">
    <p class="tool__title">Webhook signature tester</p>
    <p class="tool__hint">
      Signs a payload the way Flutterwave does (base64 HMAC-SHA256 of the raw body, keyed with your secret hash) so you can
      check a header you received or send a test webhook to your app. Everything runs in your browser. Still, use a test secret hash here, not your live one.
    </p>

    <p v-if="!supported" class="tool__status tool__status--bad">Your browser doesn't expose Web Crypto on this page. Open the docs over https or localhost.</p>

    <div class="tool__grid">
      <label>Secret hash (FLW_SECRET_HASH)
        <input v-model="secret" type="text" autocomplete="off" spellcheck="false" />
      </label>
      <label>Send to
        <input v-model="url" type="text" spellcheck="false" />
      </label>
    </div>

    <label style="margin-top: 12px">Raw request body
      <textarea v-model="body" spellcheck="false" rows="12" />
      <small v-if="!bodyValid" style="color: var(--vp-c-red-1)">Not valid JSON. The signature is still computed over the exact text.</small>
    </label>

    <label style="margin-top: 12px">flutterwave-signature header
      <input :value="signature || '...'" type="text" readonly />
    </label>

    <label style="margin-top: 12px">Check a header you received (flutterwave-signature or verif-hash)
      <input v-model="received" type="text" placeholder="paste the header value" spellcheck="false" />
    </label>
    <p v-if="verdict" :class="['tool__status', verdict.ok ? 'tool__status--ok' : 'tool__status--bad']">{{ verdict.text }}</p>
    <p v-if="error" class="tool__status tool__status--bad">{{ error }}</p>

    <p class="tool__hint" style="margin: 16px 0 0">
      Send it to your local app. A <code>charge.completed</code> with a made-up id gets a 500, because the package re-fetches every charge
      from the API before firing <code>ChargeCompleted</code>. Use a real test transaction id, or <code>php artisan flutterwave:ping-webhook &lt;id&gt;</code>.
    </p>
    <CodeOut :code="curl" />
    <CodeOut :code="php" />
  </div>
</template>
