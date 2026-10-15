<script setup lang="ts">
import { computed, reactive } from 'vue'
import CodeOut from './CodeOut.vue'

type Mode = 'hosted' | 'inline' | 'inertia-react' | 'inertia-vue' | 'inertia-svelte'

const modes: { id: Mode; label: string }[] = [
  { id: 'hosted', label: 'Hosted page' },
  { id: 'inline', label: 'Inline modal (Blade)' },
  { id: 'inertia-react', label: 'Inertia + React' },
  { id: 'inertia-vue', label: 'Inertia + Vue' },
  { id: 'inertia-svelte', label: 'Inertia + Svelte' },
]

const form = reactive({
  mode: 'hosted' as Mode,
  amount: 5000,
  currency: 'NGN',
  fromOrder: true,
  name: false,
  phone: false,
  meta: true,
  subaccount: false,
  options: '',
})

const php = computed(() => {
  const amount = form.fromOrder ? '$order->total' : String(form.amount || 0)
  const currency = form.fromOrder ? '$order->currency' : `'${form.currency}'`
  const email = form.fromOrder ? '$order->user->email' : "'customer@example.com'"
  const ref = form.fromOrder ? "    'tx_ref' => $order->reference,      // your own reference, so you can match it later\n" : ''

  const lines = [ref + `    'amount' => ${amount},`, `    'currency' => ${currency},`]

  if (form.name || form.phone) {
    // With a customer array, the email goes inside it.
    const customer = [`        'email' => ${email},`]
    if (form.name) customer.push(`        'name' => ${form.fromOrder ? '$order->user->name' : "'Ada Lovelace'"},`)
    if (form.phone) customer.push(`        'phone_number' => ${form.fromOrder ? '$order->user->phone' : "'08012345678'"},`)
    lines.push(`    'customer' => [\n${customer.join('\n')}\n    ],`)
  } else {
    lines.push(`    'email' => ${email},`)
  }

  if (form.meta) lines.push(`    'meta' => ['order_id' => ${form.fromOrder ? '$order->id' : '123'}],`)
  if (form.subaccount) lines.push(`    'subaccounts' => [['id' => 'RS_XXXXXXXX']],  // split the payment`)
  if (form.options.trim()) lines.push(`    'payment_options' => '${form.options.trim()}',`)

  const payload = `[\n${lines.join('\n')}\n]`
  const sig = form.fromOrder ? 'Order $order' : 'Request $request'
  const head = `use Flutterwave\\Payments\\Facades\\Flutterwave;\n`

  switch (form.mode) {
    case 'hosted':
      return `${head}\npublic function pay(${sig})\n{\n    // Sends the customer to Flutterwave's hosted checkout.\n    // Works with Inertia too: it returns a 409 + X-Inertia-Location for XHR requests.\n    return Flutterwave::redirect(${indent(payload)});\n}`
    case 'inline':
      return `${head}\npublic function pay(${sig})\n{\n    $payment_details = Flutterwave::render('inline', ${indent(payload)});\n\n    // The package's view opens the modal as soon as the page loads.\n    return view('flutterwave::modal', compact('payment_details'));\n}`
    default:
      return `${head}use Inertia\\Inertia;\n\npublic function show(${sig})\n{\n    return Inertia::render('Checkout', [\n        // Built on the server, so the amount can't be changed in the browser.\n        'checkout' => Flutterwave::inlineConfig(${indent(payload, 8)}),\n    ]);\n}`
  }
})

const frontend = computed(() => {
  const label = form.fromOrder ? 'Pay now' : `Pay ${form.currency} ${form.amount}`
  switch (form.mode) {
    case 'inertia-react':
      return `// resources/js/Pages/Checkout.tsx\nimport { router } from '@inertiajs/react';\nimport { FlutterwavePayButton, type FlutterwaveCheckoutConfig } from '@/components/flutterwave';\n\nexport default function Checkout({ checkout }: { checkout: FlutterwaveCheckoutConfig }) {\n    return (\n        <FlutterwavePayButton\n            config={checkout}\n            onSuccess={(payment) => router.post('/orders/confirm', { transaction_id: payment.transaction_id })}\n        >\n            ${label}\n        </FlutterwavePayButton>\n    );\n}`
    case 'inertia-vue':
      return `<!-- resources/js/Pages/Checkout.vue -->\n<script setup lang="ts">\nimport { router } from '@inertiajs/vue3';\nimport { FlutterwavePayButton, type FlutterwaveCheckoutConfig } from '@/components/flutterwave';\n\ndefineProps<{ checkout: FlutterwaveCheckoutConfig }>();\n<\/script>\n\n<template>\n    <FlutterwavePayButton\n        :config="checkout"\n        :on-success="(payment) => router.post('/orders/confirm', { transaction_id: payment.transaction_id })"\n    >\n        ${label}\n    </FlutterwavePayButton>\n</template>`
    case 'inertia-svelte':
      return `<!-- resources/js/Pages/Checkout.svelte -->\n<script lang="ts">\n    import { router } from '@inertiajs/svelte';\n    import { FlutterwavePayButton, type FlutterwaveCheckoutConfig } from '@/components/flutterwave';\n\n    let { checkout }: { checkout: FlutterwaveCheckoutConfig } = $props();\n<\/script>\n\n<FlutterwavePayButton\n    config={checkout}\n    onSuccess={(payment) => router.post('/orders/confirm', { transaction_id: payment.transaction_id })}\n>\n    ${label}\n</FlutterwavePayButton>`
    default:
      return ''
  }
})

const install = computed(() => {
  const flag = { 'inertia-react': '--react', 'inertia-vue': '--vue', 'inertia-svelte': '--svelte' }[form.mode as string]
  return flag ? `php artisan flutterwave:install ${flag}` : ''
})

function indent(text: string, by = 4) {
  return text.replace(/\n/g, '\n' + ' '.repeat(by))
}
</script>

<template>
  <div class="tool">
    <p class="tool__title">Checkout builder</p>
    <p class="tool__hint">Choose how you want to take the payment and get the controller (and page) code for it.</p>

    <div class="tool__tabs" role="group" aria-label="Checkout type">
      <button v-for="m in modes" :key="m.id" type="button" class="tool__tab" :aria-pressed="form.mode === m.id" @click="form.mode = m.id">{{ m.label }}</button>
    </div>

    <div class="tool__grid">
      <label class="tool__check"><input v-model="form.fromOrder" type="checkbox" /> Use values from an Order model</label>
      <label v-if="!form.fromOrder">Amount <input v-model.number="form.amount" type="number" min="1" /></label>
      <label v-if="!form.fromOrder">Currency
        <select v-model="form.currency"><option v-for="c in ['NGN','GHS','KES','UGX','TZS','ZAR','RWF','XAF','XOF','USD','EUR','GBP']" :key="c">{{ c }}</option></select>
      </label>
      <label class="tool__check"><input v-model="form.name" type="checkbox" /> Customer name</label>
      <label class="tool__check"><input v-model="form.phone" type="checkbox" /> Customer phone</label>
      <label class="tool__check"><input v-model="form.meta" type="checkbox" /> Meta (order id)</label>
      <label class="tool__check"><input v-model="form.subaccount" type="checkbox" /> Split to a subaccount</label>
      <label>Payment options <input v-model="form.options" type="text" placeholder="card,banktransfer,ussd" /><small>Empty uses paymentType from the config</small></label>
    </div>

    <p v-if="install" class="tool__hint" style="margin: 16px 0 0">Publish the components once:</p>
    <CodeOut v-if="install" :code="install" />
    <CodeOut :code="php" />
    <CodeOut v-if="frontend" :code="frontend" />
  </div>
</template>
