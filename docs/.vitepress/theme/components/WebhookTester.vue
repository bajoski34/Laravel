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

const php = `use Flutterwave\\Payments\\Facades\\Flutterwave;
use Flutterwave\\Payments\\Testing\\FlutterwaveFake;

it('marks the order paid when the charge completes', function () {
    // The fake answers the package's re-verification of the charge.
    Flutterwave::fake()->transaction(4975363, ['amount' => 5000, 'tx_ref' => 'ORDER-42']);

    $this->postJson('/flutterwave/webhook', [
        'event' => 'charge.completed',
        'data' => ['id' => 4975363, 'tx_ref' => 'ORDER-42', 'status' => 'successful'],
    ], FlutterwaveFake::webhookHeaders())->assertOk();
});`
</script>

<template>
  <div class="tool">
    <div class="tool__head">
      <p class="tool__title">Webhook signature tester</p>
      <p class="tool__hint">
        Signs a payload the way Flutterwave does: a base64 HMAC-SHA256 of the raw body, keyed with your secret hash.
        Check a header you received, or send a test webhook to your app. It all runs in your browser, but use a test
        secret hash here, not your live one.
      </p>
    </div>

    <div class="tool__body">
      <p v-if="!supported" class="tool__status tool__status--bad">This browser doesn't expose Web Crypto here. Open the docs over https or localhost.</p>

      <div class="tool__fields">
        <label class="tool__field">
          <span class="tool__label">Secret hash</span>
          <input v-model="secret" type="text" autocomplete="off" spellcheck="false" />
          <span class="tool__note">The value of <code>FLW_SECRET_HASH</code></span>
        </label>
        <label class="tool__field">
          <span class="tool__label">Send to</span>
          <input v-model="url" type="text" spellcheck="false" />
          <span class="tool__note">Your app's webhook URL</span>
        </label>
      </div>

      <label class="tool__field">
        <span class="tool__label">Raw request body</span>
        <textarea v-model="body" spellcheck="false" rows="12" />
        <span v-if="!bodyValid" class="tool__note" style="color: var(--vp-c-red-1)">Not valid JSON. The signature still covers the exact text.</span>
      </label>

      <label class="tool__field">
        <span class="tool__label">flutterwave-signature header</span>
        <input :value="signature || 'Waiting for a secret hash'" type="text" readonly />
      </label>

      <label class="tool__field">
        <span class="tool__label">Check a header you received</span>
        <input v-model="received" type="text" placeholder="Paste a flutterwave-signature or verif-hash value" spellcheck="false" />
      </label>
      <p v-if="verdict" :class="['tool__status', verdict.ok ? 'tool__status--ok' : 'tool__status--bad']">{{ verdict.text }}</p>
      <p v-if="error" class="tool__status tool__status--bad">{{ error }}</p>
    </div>

    <div class="tool__out">
      <p class="tool__out-label">
        Send it to your local app. A <code>charge.completed</code> with a made-up id gets a 500, because the package
        re-fetches every charge from the API before firing <code>ChargeCompleted</code>. Use a real test transaction id,
        or <code>php artisan flutterwave:ping-webhook &lt;id&gt;</code>.
      </p>
      <CodeOut :code="curl" lang="shellscript" title="Terminal" />
      <CodeOut :code="php" lang="php" title="tests/Feature/WebhookTest.php" />
    </div>
  </div>
</template>
