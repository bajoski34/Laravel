import { defineConfig } from 'vitepress'
import llmstxt from 'vitepress-plugin-llms'

// GitHub Pages serves project sites from /<repo>/. Override with DOCS_BASE=/ for a custom domain.
const base = process.env.DOCS_BASE ?? '/Laravel/'

// Where the site is served, for the sitemap and the absolute links in llms.txt.
// Set DOCS_ORIGIN when moving to a custom domain.
const origin = process.env.DOCS_ORIGIN ?? 'https://bajoski34.github.io'

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

  sitemap: { hostname: `${origin}${base}` },

  vite: {
    // The fonts live in the package's src/, one level above the docs.
    server: { fs: { allow: ['..'] } },
    plugins: [
      // llms.txt, llms-full.txt and a Markdown copy of every page, for AI tools and crawlers.
      // The "Copy page" menu reads those per-page .md files.
      llmstxt({
        domain: origin, // the plugin adds the base path itself
        // Purely interactive, so empty as text.
        ignoreFiles: ['tools/index.md'],
        customTemplateVariables: {
          title: 'Flutterwave for Laravel',
          description:
            'The Laravel package for Flutterwave (abraham-flutterwave/laravel-payment): checkout, payouts, subscriptions, webhooks and more from one facade.',
          details:
            'Install with `composer require abraham-flutterwave/laravel-payment`, then `php artisan flutterwave:install`. Supports PHP 8.1+ and Laravel 9 to 13. Every page below is also available as Markdown by adding .md to its URL.',
        },
      }),
    ],
  },

  markdown: {
    theme: { light: 'github-light', dark: 'github-dark' },
  },

  themeConfig: {
    logo: '/logo.svg',

    // Labels for the "Copy page" menu.
    llms: {
      copyText: 'Copy page',
      copiedText: 'Copied',
      viewMarkdownText: 'View as Markdown',
      openInAIText: 'Open in {provider}',
    },

    nav: [
      { text: 'Guide', link: '/guide/getting-started', activeMatch: '/guide/' },
      { text: 'Reference', link: '/reference/services', activeMatch: '/reference/' },
      { text: 'Tools', link: '/tools/' },
      // A file, not a page: VitePress leaves new-tab links alone, so the base is added here.
      { text: 'PDF guide', link: `${base}downloads/flutterwave-laravel-guide.pdf`, target: '_blank', rel: 'noopener' },
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
