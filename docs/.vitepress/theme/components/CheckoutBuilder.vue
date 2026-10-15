<script setup lang="ts">
import { computed, reactive } from 'vue'
import CodeOut from './CodeOut.vue'
import type { CodeLang } from '../highlight'

type Mode = 'hosted' | 'inline' | 'react' | 'vue' | 'svelte'

const modes: { id: Mode; label: string }[] = [
  { id: 'hosted', label: 'Hosted page' },
  { id: 'inline', label: 'Inline modal' },
  { id: 'react', label: 'Inertia + React' },
  { id: 'vue', label: 'Inertia + Vue' },
  { id: 'svelte', label: 'Inertia + Svelte' },
]

const currencies = ['NGN', 'GHS', 'KES', 'UGX', 'TZS', 'ZAR', 'RWF', 'XAF', 'XOF', 'USD', 'EUR', 'GBP']

const form = reactive({
  mode: 'hosted' as Mode,
  source: 'order' as 'order' | 'example',
  amount: 5000,
  currency: 'NGN',
  name: false,
  phone: false,
  meta: true,
  subaccount: false,
  options: '',
})

const fromOrder = computed(() => form.source === 'order')
const inertia = computed(() => form.mode === 'react' || form.mode === 'vue' || form.mode === 'svelte')

const indent = (text: string, by: number) => text.replace(/\n/g, '\n' + ' '.repeat(by))

const payload = computed(() => {
  const o = fromOrder.value
  const email = o ? '$order->user->email' : "'customer@example.com'"
  const lines: string[] = []

  if (o) lines.push("    // Your own reference, to match the payment later.", "    'tx_ref' => $order->reference,")
  lines.push(`    'amount' => ${o ? '$order->total' : form.amount || 0},`)
  lines.push(`    'currency' => ${o ? '$order->currency' : `'${form.currency}'`},`)

  if (form.name || form.phone) {
    // With a customer array, the email goes inside it.
    const c = [`        'email' => ${email},`]
    if (form.name) c.push(`        'name' => ${o ? '$order->user->name' : "'Ada Lovelace'"},`)
    if (form.phone) c.push(`        'phone_number' => ${o ? '$order->user->phone' : "'08012345678'"},`)
    lines.push(`    'customer' => [\n${c.join('\n')}\n    ],`)
  } else {
    lines.push(`    'email' => ${email},`)
  }

  if (form.meta) lines.push(`    'meta' => ['order_id' => ${o ? '$order->id' : '42'}],`)
  if (form.subaccount) lines.push("    'subaccounts' => [['id' => 'RS_XXXXXXXX']],")
  if (form.options.trim()) lines.push(`    'payment_options' => '${form.options.trim()}',`)

  return `[\n${lines.join('\n')}\n]`
})

const php = computed(() => {
  const arg = fromOrder.value ? 'Order $order' : 'Request $request'
  const use = 'use Flutterwave\\Payments\\Facades\\Flutterwave;'

  if (form.mode === 'hosted') {
    return `${use}

public function pay(${arg})
{
    // Sends the customer to Flutterwave's hosted checkout.
    // Inertia form posts work too, with no extra code.
    return Flutterwave::redirect(${indent(payload.value, 4)});
}`
  }

  if (form.mode === 'inline') {
    return `${use}

public function pay(${arg})
{
    $payment_details = Flutterwave::render('inline', ${indent(payload.value, 4)});

    // The package's view opens the modal when the page loads.
    return view('flutterwave::modal', compact('payment_details'));
}`
  }

  return `${use}
use Inertia\\Inertia;

public function show(${arg})
{
    return Inertia::render('Checkout', [
        // Built on the server, so the amount can't be changed in the browser.
        'checkout' => Flutterwave::inlineConfig(${indent(payload.value, 8)}),
    ]);
}`
})

const label = computed(() => (fromOrder.value ? 'Pay now' : `Pay ${form.currency} ${form.amount}`))
const onSuccess = "(payment) => router.post('/orders/confirm', { transaction_id: payment.transaction_id })"

const page = computed<{ code: string; lang: CodeLang; title: string } | null>(() => {
  switch (form.mode) {
    case 'react':
      return {
        lang: 'tsx',
        title: 'resources/js/Pages/Checkout.tsx',
        code: `import { router } from '@inertiajs/react';
import { FlutterwavePayButton, type FlutterwaveCheckoutConfig } from '@/components/flutterwave';

export default function Checkout({ checkout }: { checkout: FlutterwaveCheckoutConfig }) {
    return (
        <FlutterwavePayButton
            config={checkout}
            onSuccess={${onSuccess}}
        >
            ${label.value}
        </FlutterwavePayButton>
    );
}`,
      }
    case 'vue':
      return {
        lang: 'vue',
        title: 'resources/js/Pages/Checkout.vue',
        code: `<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { FlutterwavePayButton, type FlutterwaveCheckoutConfig } from '@/components/flutterwave';

defineProps<{ checkout: FlutterwaveCheckoutConfig }>();
<\/script>

<template>
    <FlutterwavePayButton
        :config="checkout"
        :on-success="${onSuccess}"
    >
        ${label.value}
    </FlutterwavePayButton>
</template>`,
      }
    case 'svelte':
      return {
        lang: 'svelte',
        title: 'resources/js/Pages/Checkout.svelte',
        code: `<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { FlutterwavePayButton, type FlutterwaveCheckoutConfig } from '@/components/flutterwave';

    let { checkout }: { checkout: FlutterwaveCheckoutConfig } = $props();
<\/script>

<FlutterwavePayButton
    config={checkout}
    onSuccess={${onSuccess}}
>
    ${label.value}
</FlutterwavePayButton>`,
      }
    default:
      return null
  }
})

const install = computed(() => (inertia.value ? `php artisan flutterwave:install --${form.mode}` : ''))
</script>

<template>
  <div class="tool">
    <div class="tool__head">
      <p class="tool__title">Checkout builder</p>
      <p class="tool__hint">Choose how you want to take the payment. You get the controller, and for Inertia the page component too.</p>
    </div>

    <div class="tool__body">
      <div class="tool__row">
        <span class="tool__label" id="cb-mode">Checkout</span>
        <div class="tool__seg" role="group" aria-labelledby="cb-mode">
          <button v-for="m in modes" :key="m.id" type="button" :aria-pressed="form.mode === m.id" @click="form.mode = m.id">{{ m.label }}</button>
        </div>
      </div>

      <div class="tool__row">
        <span class="tool__label" id="cb-source">Values</span>
        <div class="tool__seg" role="group" aria-labelledby="cb-source">
          <button type="button" :aria-pressed="form.source === 'order'" @click="form.source = 'order'">From an Order model</button>
          <button type="button" :aria-pressed="form.source === 'example'" @click="form.source = 'example'">Example values</button>
        </div>
      </div>

      <div v-if="!fromOrder" class="tool__fields">
        <label class="tool__field">
          <span class="tool__label">Amount</span>
          <input v-model.number="form.amount" type="number" min="1" />
        </label>
        <label class="tool__field">
          <span class="tool__label">Currency</span>
          <select v-model="form.currency"><option v-for="c in currencies" :key="c">{{ c }}</option></select>
        </label>
      </div>

      <div class="tool__row">
        <span class="tool__label">Include</span>
        <div class="tool__chips">
          <label class="tool__chip"><input v-model="form.name" type="checkbox" /> Customer name</label>
          <label class="tool__chip"><input v-model="form.phone" type="checkbox" /> Customer phone</label>
          <label class="tool__chip"><input v-model="form.meta" type="checkbox" /> Order id in meta</label>
          <label class="tool__chip"><input v-model="form.subaccount" type="checkbox" /> Split to a subaccount</label>
        </div>
      </div>

      <div class="tool__fields">
        <label class="tool__field">
          <span class="tool__label">Payment methods</span>
          <input v-model="form.options" type="text" placeholder="card,banktransfer,ussd" />
          <span class="tool__note">Leave empty to use <code>paymentType</code> from the config.</span>
        </label>
      </div>
    </div>

    <div class="tool__out">
      <CodeOut v-if="install" :code="install" lang="shellscript" title="Terminal: publish the components once" />
      <CodeOut :code="php" lang="php" title="app/Http/Controllers/CheckoutController.php" />
      <CodeOut v-if="page" :code="page.code" :lang="page.lang" :title="page.title" />
    </div>
  </div>
</template>
