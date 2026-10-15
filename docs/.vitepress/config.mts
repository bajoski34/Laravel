import { defineConfig } from 'vitepress'

// GitHub Pages serves project sites from /<repo>/. Override with DOCS_BASE=/ for a custom domain.
const base = process.env.DOCS_BASE ?? '/Laravel/'

export default defineConfig({
  base,
  title: 'Flutterwave Laravel',
  description: 'Accept payments, send payouts, run subscriptions and handle webhooks with Flutterwave in Laravel.',
  lang: 'en-GB',
  cleanUrls: true,
  lastUpdated: true,

  head: [
    ['meta', { name: 'theme-color', content: '#ff9b00' }],
    ['link', { rel: 'icon', type: 'image/svg+xml', href: `${base}logo.svg` }],
  ],

  // The fonts live in the package's src/, one level above the docs.
  vite: { server: { fs: { allow: ['..'] } } },

  markdown: {
    theme: { light: 'github-light', dark: 'github-dark' },
  },

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/guide/getting-started', activeMatch: '/guide/' },
      { text: 'Reference', link: '/reference/services', activeMatch: '/reference/' },
      { text: 'Tools', link: '/tools/' },
      {
        text: 'v2.3',
        items: [
          { text: 'Changelog', link: 'https://github.com/bajoski34/Laravel/releases' },
          { text: 'Packagist', link: 'https://packagist.org/packages/abraham-flutterwave/laravel-payment' },
          { text: 'Flutterwave API docs', link: 'https://developer.flutterwave.com' },
        ],
      },
    ],

    sidebar: {
      '/guide/': [
        {
          text: 'Introduction',
          items: [
            { text: 'Getting started', link: '/guide/getting-started' },
            { text: 'Configuration', link: '/guide/configuration' },
          ],
        },
        {
          text: 'Taking payments',
          items: [
            { text: 'Checkout', link: '/guide/checkout' },
            { text: 'Verifying payments', link: '/guide/verifying-payments' },
            { text: 'Inertia (React, Vue, Svelte)', link: '/guide/inertia' },
            { text: 'Saved cards', link: '/guide/saved-cards' },
            { text: 'Refunds', link: '/guide/refunds' },
          ],
        },
        {
          text: 'Moving money',
          items: [
            { text: 'Transfers', link: '/guide/transfers' },
            { text: 'Virtual accounts', link: '/guide/virtual-accounts' },
            { text: 'Subscriptions', link: '/guide/subscriptions' },
            { text: 'FX and bills', link: '/guide/fx-and-bills' },
          ],
        },
        {
          text: 'Operations',
          items: [
            { text: 'Webhooks', link: '/guide/webhooks' },
            { text: 'Chargebacks', link: '/guide/chargebacks' },
            { text: 'Admin page and components', link: '/guide/admin' },
            { text: 'Testing', link: '/guide/testing' },
          ],
        },
      ],
      '/reference/': [
        {
          text: 'Reference',
          items: [
            { text: 'Services', link: '/reference/services' },
            { text: 'Artisan commands', link: '/reference/artisan' },
            { text: 'Events', link: '/reference/events' },
            { text: 'Config options', link: '/reference/config' },
          ],
        },
      ],
    },

    search: { provider: 'local' },

    editLink: {
      pattern: 'https://github.com/bajoski34/Laravel/edit/2.x/docs/:path',
      text: 'Edit this page on GitHub',
    },

    socialLinks: [{ icon: 'github', link: 'https://github.com/bajoski34/Laravel' }],

    outline: { level: [2, 3] },

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © Flutterwave Inc.',
    },
  },
})
