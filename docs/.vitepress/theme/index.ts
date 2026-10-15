import DefaultTheme from 'vitepress/theme-without-fonts'
import type { Theme } from 'vitepress'
import './style.css'
import './tools.css'

import EnvBuilder from './components/EnvBuilder.vue'
import CheckoutBuilder from './components/CheckoutBuilder.vue'
import WebhookTester from './components/WebhookTester.vue'
import ServiceExplorer from './components/ServiceExplorer.vue'
import ArtisanBuilder from './components/ArtisanBuilder.vue'
import HomeLanding from './components/HomeLanding.vue'
import Layout from './Layout.vue'

export default {
  extends: DefaultTheme,
  Layout,
  enhanceApp({ app }) {
    app.component('EnvBuilder', EnvBuilder)
    app.component('CheckoutBuilder', CheckoutBuilder)
    app.component('WebhookTester', WebhookTester)
    app.component('ServiceExplorer', ServiceExplorer)
    app.component('ArtisanBuilder', ArtisanBuilder)
    app.component('HomeLanding', HomeLanding)
  },
} satisfies Theme
