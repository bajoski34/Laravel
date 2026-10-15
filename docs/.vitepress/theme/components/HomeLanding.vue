<script setup lang="ts">
import { ref } from 'vue'
import { withBase } from 'vitepress'
import services from '../../data/services.json'
import commands from '../../data/artisan.json'

const methodCount = (services as { methods: unknown[] }[]).reduce((n, s) => n + s.methods.length, 0)

// The hero's code window follows one payment through the app.
const steps = [
  { id: 'checkout', label: 'Checkout', file: 'app/Http/Controllers/CheckoutController.php' },
  { id: 'webhook', label: 'Webhook', file: 'app/Listeners/FulfilOrder.php' },
  { id: 'payout', label: 'Payout', file: 'app/Jobs/PaySeller.php' },
  { id: 'test', label: 'Test', file: 'tests/Feature/CheckoutTest.php' },
] as const
const active = ref<(typeof steps)[number]['id']>('checkout')

function onKey(e: KeyboardEvent, index: number) {
  const dir = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0
  if (!dir) return
  const next = steps[(index + dir + steps.length) % steps.length]
  active.value = next.id
  ;(document.getElementById(`hero-tab-${next.id}`) as HTMLElement | null)?.focus()
}

const install = 'composer require abraham-flutterwave/laravel-payment'
const copied = ref(false)
async function copyInstall() {
  try {
    await navigator.clipboard.writeText(install)
    copied.value = true
    setTimeout(() => (copied.value = false), 1600)
  } catch {
    /* clipboard blocked: the command is still selectable */
  }
}

const builds = [
  {
    title: 'An online store',
    text: 'Send customers to checkout and mark orders paid once Flutterwave confirms the money arrived.',
    calls: ['Flutterwave::redirect()', 'ChargeCompleted'],
    link: '/guide/checkout',
  },
  {
    title: 'Subscription billing',
    text: 'Charge on a plan Flutterwave runs, or save the card and bill it on your own schedule.',
    calls: ['plans()->create()', '$user->chargeSavedCard()'],
    link: '/guide/subscriptions',
  },
  {
    title: 'A marketplace',
    text: 'Split each payment with sellers and pay them out to their bank or mobile money wallet.',
    calls: ["'subaccounts' => [...]", 'transfers()->create()'],
    link: '/guide/transfers',
  },
  {
    title: 'Pay by bank transfer',
    text: 'Give each order its own account number that expires, and check customers with BVN consent.',
    calls: ['virtualAccounts()->dynamicFor()', 'bvn()->matches()'],
    link: '/guide/virtual-accounts',
  },
  {
    title: 'Airtime and bills',
    text: 'Sell airtime, data, TV and electricity, with the meter or smartcard checked before you pay.',
    calls: ['bills()->payValidated()'],
    link: '/guide/fx-and-bills#bill-payments',
  },
  {
    title: 'Treasury',
    text: 'Move money between currencies at a locked rate, or send USDC and USDT to a wallet.',
    calls: ['fx()->tradeIfReady()', 'stablecoins()->send()'],
    link: '/guide/fx-and-bills',
  },
  {
    title: 'A back office',
    text: 'Give your team refunds, settlements and chargeback responses behind your own login.',
    calls: ['Route::flutterwaveAdmin()'],
    link: '/guide/admin',
  },
]

const frontends = [
  { name: 'Blade', tag: '<x-flutterwave-button amount="5000" />', link: '/guide/admin#blade-components' },
  { name: 'React', tag: '<FlutterwavePayButton config={checkout} />', link: '/guide/inertia' },
  { name: 'Vue', tag: '<FlutterwavePayButton :config="checkout" />', link: '/guide/inertia' },
  { name: 'Svelte', tag: '<FlutterwavePayButton config={checkout} />', link: '/guide/inertia' },
]

const faqs = [
  {
    q: 'Which Flutterwave API does it use?',
    a: 'Version 3, at api.flutterwave.com/v3, with the v3 inline checkout script. Your v3 test and live keys work as they are.',
  },
  {
    q: 'Do I need webhooks?',
    a: 'For anything you fulfil, yes. Customers close tabs before the redirect, and bank transfers and USSD payments can finish minutes later. The webhook route is registered for you; you only listen for ChargeCompleted.',
  },
  {
    q: 'How do I test without moving real money?',
    a: 'Use test keys and Flutterwave’s test cards. In your test suite, Flutterwave::fake() answers every API call, and sandbox transfers take a test_outcome so you choose whether they succeed.',
  },
  {
    q: 'How do I receive webhooks on localhost?',
    a: 'Run php artisan flutterwave:ping-webhook with a test transaction id. It signs a real payload and posts it to your local route, so nothing has to be exposed to the internet.',
  },
  {
    q: 'Can I change how a service behaves?',
    a: 'Map its key to your own class in config/flutterwave.php, or to null to turn it off. Your class receives the same constructor arguments as the built-in one.',
  },
]
</script>

<template>
  <div class="landing">
    <section class="hero">
      <div class="hero__copy">
        <h1 class="hero__title">Take payments across Africa from your Laravel app</h1>
        <p class="hero__lead">
          One facade for checkout, payouts, subscriptions and virtual accounts. Webhooks arrive verified and
          deduplicated, as ordinary Laravel events.
        </p>

        <div class="hero__actions">
          <a class="btn btn--primary" :href="withBase('/guide/getting-started')">Get started</a>
          <a class="btn btn--quiet" :href="withBase('/tools/')">Try the tools</a>
        </div>

        <div class="install">
          <code class="install__cmd">{{ install }}</code>
          <button type="button" class="install__copy" @click="copyInstall">{{ copied ? 'Copied' : 'Copy' }}</button>
        </div>

        <p class="hero__specs">
          PHP 8.1 and up, Laravel 9 to 13. {{ services.length }} services with {{ methodCount }} methods,
          {{ commands.length }} artisan commands, and pay buttons for Blade, React, Vue and Svelte.
        </p>
      </div>

      <div class="window" aria-label="A payment through the package, step by step">
        <div class="window__bar">
          <div class="window__tabs" role="tablist">
            <button
              v-for="(s, i) in steps"
              :id="`hero-tab-${s.id}`"
              :key="s.id"
              type="button"
              role="tab"
              class="window__tab"
              :aria-selected="active === s.id"
              :aria-controls="`hero-panel-${s.id}`"
              :tabindex="active === s.id ? 0 : -1"
              @click="active = s.id"
              @keydown="onKey($event, i)"
            >
              {{ s.label }}
            </button>
          </div>
        </div>
        <div
          v-for="s in steps"
          v-show="active === s.id"
          :id="`hero-panel-${s.id}`"
          :key="s.id"
          class="window__panel"
          role="tabpanel"
          :aria-labelledby="`hero-tab-${s.id}`"
        >
          <p class="window__file">{{ s.file }}</p>
          <slot :name="s.id" />
        </div>
      </div>
    </section>

    <section class="builds">
      <div class="section-head">
        <h2>What will you build with Flutterwave today?</h2>
        <p>Each one is a guide with the calls it needs.</p>
      </div>
      <ul class="builds__list">
        <li v-for="b in builds" :key="b.title">
          <a class="build" :href="withBase(b.link)">
            <span class="build__title">{{ b.title }}</span>
            <span class="build__text">{{ b.text }}</span>
            <span class="build__calls">
              <code v-for="c in b.calls" :key="c">{{ c }}</code>
            </span>
          </a>
        </li>
      </ul>
    </section>

    <section class="frontends">
      <div class="section-head">
        <h2>A pay button for the frontend you already use</h2>
        <p>
          Blade components work with no build step. The React, Vue and Svelte components are tested on Inertia 1, 2
          and 3, and publish into your app so you can edit them.
        </p>
      </div>
      <ul class="frontends__list">
        <li v-for="f in frontends" :key="f.name" class="frontends__item">
          <a class="frontend" :href="withBase(f.link)">
            <span class="frontend__name">{{ f.name }}</span>
            <span class="frontend__tag"><code>{{ f.tag }}</code></span>
          </a>
        </li>
      </ul>
    </section>

    <section class="faq">
      <div class="section-head">
        <h2>Frequently asked questions</h2>
      </div>
      <div class="faq__list">
        <details v-for="f in faqs" :key="f.q" class="faq__item">
          <summary>{{ f.q }}</summary>
          <p>{{ f.a }}</p>
        </details>
      </div>
    </section>

    <section class="closing">
      <div class="closing__inner">
        <h2>Ready to build something awesome?</h2>
        <p>Install the package, add your keys and take a test payment in about ten minutes.</p>
        <div class="hero__actions">
          <a class="btn btn--primary" :href="withBase('/guide/getting-started')">Get started</a>
          <a class="btn btn--on-dark" :href="withBase('/reference/services')">Browse the API</a>
          <a class="btn btn--on-dark" :href="withBase('/downloads/flutterwave-laravel-guide.pdf')" download>Download the PDF guide</a>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.landing {
  --ink: #101828;
  --slate: #475467;
  --line: #eaecf0;
  --subtle: #f9fafb;
  --orange: #ff9b00;
  --orange-hover: #e08c00;
  --orange-tint: #fff4e0;
  --serif: 'Millik', 'Iowan Old Style', Palatino, Georgia, serif;

  color: var(--vp-c-text-1);
}

.dark .landing {
  --line: #2a303b;
  --subtle: #161b22;
  --slate: #c4cad4;
  --orange-tint: #3a2a0f;
}

.landing > section {
  max-width: 1152px;
  margin: 0 auto;
  padding: 0 24px;
}

h1,
h2 {
  font-family: var(--serif);
  font-weight: 400;
  letter-spacing: -0.015em;
  color: var(--fw-heading);
  margin: 0;
  border: 0;
  padding: 0;
}

/* ---------------------------------------------------------------- hero */

.hero {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.08fr);
  gap: 56px;
  align-items: center;
  padding-top: 72px !important;
  padding-bottom: 88px !important;
}

.hero__title {
  font-size: clamp(38px, 5vw, 58px);
  line-height: 1.04;
  max-width: 13ch;
}

.hero__lead {
  margin: 24px 0 0;
  max-width: 46ch;
  font-size: 18px;
  line-height: 1.6;
  color: var(--slate);
}

.hero__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 32px;
}

.btn {
  display: inline-flex;
  align-items: center;
  height: 44px;
  padding: 0 22px;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 500;
  text-decoration: none;
  transition: background-color 0.15s, border-color 0.15s;
}

.btn--primary {
  background: var(--orange);
  color: #1a1a1a;
}
.btn--primary:hover {
  background: var(--orange-hover);
}

.btn--quiet {
  border: 1px solid var(--vp-c-divider);
  color: var(--vp-c-text-1);
}
.btn--quiet:hover {
  border-color: var(--vp-c-text-3);
}

.btn--on-dark {
  border: 1px solid rgba(255, 255, 255, 0.28);
  color: #fff;
}
.btn--on-dark:hover {
  border-color: rgba(255, 255, 255, 0.6);
}

.btn:focus-visible,
.install__copy:focus-visible,
.window__tab:focus-visible,
.build:focus-visible,
.frontend:focus-visible,
.faq__item summary:focus-visible {
  outline: 2px solid var(--orange);
  outline-offset: 2px;
}

.install {
  display: flex;
  align-items: center;
  max-width: 100%;
  margin-top: 20px;
  border: 1px solid var(--line);
  border-radius: 8px;
  background: var(--subtle);
}

.install__cmd {
  flex: 1;
  min-width: 0;
  padding: 10px 14px;
  overflow-x: auto;
  white-space: nowrap;
  font-size: 13px;
  background: none;
  color: var(--vp-c-text-1);
}

.install__copy {
  align-self: stretch;
  padding: 0 14px;
  border-left: 1px solid var(--line);
  font-size: 13px;
  color: var(--vp-c-text-2);
}
.install__copy:hover {
  color: var(--vp-c-text-1);
}

.hero__specs {
  margin: 20px 0 0;
  max-width: 52ch;
  font-size: 14px;
  line-height: 1.6;
  color: var(--vp-c-text-2);
}

/* The code window is the one dark, loud thing on the page. */
.window {
  min-width: 0;
  border-radius: 14px;
  background: var(--ink);
  box-shadow: 0 30px 60px -30px rgba(16, 24, 40, 0.55);
  overflow: hidden;
}

.dark .window {
  background: #0d1320;
  box-shadow: 0 0 0 1px #232b3a;
}

.window__bar {
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  overflow-x: auto;
}

.window__tabs {
  display: flex;
  padding: 0 12px;
}

.window__tab {
  position: relative;
  padding: 14px 14px 13px;
  font-size: 14px;
  color: rgba(255, 255, 255, 0.55);
  white-space: nowrap;
}
.window__tab:hover {
  color: rgba(255, 255, 255, 0.85);
}
.window__tab[aria-selected='true'] {
  color: #fff;
}
.window__tab[aria-selected='true']::after {
  content: '';
  position: absolute;
  left: 14px;
  right: 14px;
  bottom: -1px;
  height: 2px;
  background: var(--orange);
}

.window__panel {
  padding: 18px 0 8px;
}

.window__file {
  margin: 0;
  padding: 0 24px;
  font-family: var(--vp-font-family-mono);
  font-size: 12px;
  color: rgba(255, 255, 255, 0.45);
}

/* Code fences from index.md, always in the dark theme here. */
.window :deep(div[class*='language-']) {
  margin: 0;
  border-radius: 0;
  background: transparent;
}
.window :deep(div[class*='language-'] pre) {
  padding: 12px 24px 18px;
}
.window :deep(div[class*='language-'] code) {
  padding: 0;
  font-size: 13.5px;
  line-height: 1.75;
}
.window :deep(.vp-code span) {
  color: var(--shiki-dark, inherit);
}
.window :deep(.lang),
.window :deep(button.copy) {
  display: none;
}

/* ---------------------------------------------------------------- sections */

.section-head {
  max-width: 64ch;
  margin-bottom: 32px;
}

.section-head h2 {
  font-size: clamp(28px, 3.4vw, 38px);
  line-height: 1.15;
}

.section-head p {
  margin: 12px 0 0;
  font-size: 16px;
  line-height: 1.6;
  color: var(--vp-c-text-2);
}

.builds,
.frontends,
.faq {
  padding-top: 72px !important;
  padding-bottom: 8px !important;
}

/* Rows rather than cards: each use case is read across, from need to the calls that meet it. */
.builds__list {
  list-style: none;
  margin: 0;
  padding: 0;
  border-top: 1px solid var(--line);
}

.build {
  display: grid;
  grid-template-columns: 220px minmax(0, 1fr) minmax(0, 300px);
  gap: 8px 32px;
  align-items: baseline;
  padding: 22px 4px;
  border-bottom: 1px solid var(--line);
  text-decoration: none;
  color: inherit;
}

.build:hover .build__title {
  color: var(--vp-c-brand-1);
}

.build__title {
  font-family: var(--serif);
  font-size: 22px;
  line-height: 1.25;
  color: var(--fw-heading);
  transition: color 0.15s;
}

.build__text {
  font-size: 15px;
  line-height: 1.6;
  color: var(--vp-c-text-2);
  max-width: 52ch;
}

.build__calls {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
}

.build__calls code,
.frontend code {
  padding: 2px 8px;
  border-radius: 5px;
  font-size: 13px;
  background: var(--orange-tint);
  color: var(--vp-c-text-1);
  white-space: nowrap;
}

.frontends__list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  list-style: none;
  margin: 0;
  padding: 0;
  border-top: 1px solid var(--line);
}

.frontends__item {
  margin: 0;
  min-width: 0;
  border-bottom: 1px solid var(--line);
}

.frontends__item:nth-child(even) {
  border-left: 1px solid var(--line);
}

.frontend {
  display: block;
  height: 100%;
  padding: 20px;
  text-decoration: none;
  color: inherit;
}

.frontends__item:nth-child(odd) .frontend {
  padding-left: 0;
}

.frontend__name {
  display: block;
  font-weight: 500;
  font-size: 16px;
  color: var(--vp-c-text-1);
}

.frontend__tag {
  display: block;
  margin-top: 10px;
  overflow-x: auto;
}

.frontend:hover .frontend__name {
  color: var(--vp-c-brand-1);
}

.faq__list {
  max-width: 760px;
  border-top: 1px solid var(--line);
}

.faq__item {
  border-bottom: 1px solid var(--line);
}

.faq__item summary {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding: 20px 0;
  font-size: 17px;
  font-weight: 500;
  cursor: pointer;
  list-style: none;
}
.faq__item summary::-webkit-details-marker {
  display: none;
}
.faq__item summary::after {
  content: '+';
  font-size: 22px;
  line-height: 1;
  color: var(--vp-c-text-3);
}
.faq__item[open] summary::after {
  content: '\2212';
}

.faq__item p {
  margin: 0 0 22px;
  max-width: 64ch;
  font-size: 15px;
  line-height: 1.65;
  color: var(--vp-c-text-2);
}

.closing {
  max-width: none !important;
  margin-top: 96px !important;
  padding: 0 !important;
  background: var(--ink);
}

.dark .closing {
  background: #0d1320;
  border-top: 1px solid #232b3a;
}

.closing__inner {
  max-width: 1152px;
  margin: 0 auto;
  padding: 80px 24px 88px;
}

.closing h2 {
  font-size: clamp(30px, 4vw, 46px);
  line-height: 1.1;
  color: #fff;
}

.closing p {
  margin: 14px 0 0;
  font-size: 17px;
  color: rgba(255, 255, 255, 0.7);
}

/* ---------------------------------------------------------------- small screens */

@media (max-width: 960px) {
  .hero {
    grid-template-columns: minmax(0, 1fr);
    gap: 44px;
    padding-top: 48px !important;
    padding-bottom: 64px !important;
  }
  .build {
    grid-template-columns: minmax(0, 1fr);
  }
  .build__calls {
    flex-direction: row;
    flex-wrap: wrap;
    margin-top: 6px;
  }
}

@media (max-width: 560px) {
  .landing > section {
    padding: 0 16px;
  }
  .frontends__list {
    grid-template-columns: minmax(0, 1fr);
  }
  .frontends__item:nth-child(even) {
    border-left: 0;
  }
  .frontend {
    padding-left: 0;
  }
  .closing__inner {
    padding: 64px 16px 72px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .btn,
  .build__title {
    transition: none;
  }
}
</style>
