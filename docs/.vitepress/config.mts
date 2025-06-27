import { defineConfig } from 'vitepress'

// https://vitepress.dev/reference/site-config
export default defineConfig({
  title: "Hunter",
  description: "Powerful utility for finding and processing Eloquent model records with a fluent, chainable API.",

  head: [['link', { rel: 'icon', href: '/Hunter_icon_purple-600.png' }]],

  themeConfig: {
    logo: {
      light: '/Hunter_icon_purple-600.png',
      dark: '/Hunter_icon_zinc-50.png'
    },
    
    nav: [
      { text: 'Home', link: '/' },
      { text: 'Examples', link: '/examples' }
    ],

    sidebar: [
      {
        text: 'Getting Started',
        items: [
          { text: 'Introduction', link: '/' },
        ]
      },
      {
        text: 'Examples',
        items: [
          { text: 'Usage', link: '/examples' },
        ]
      },
      {
        text: 'API Reference',
        items: [
          { text: 'Hunter Class', link: '/api' },
        ]
      }
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/e2tmk/hunter' }
    ],

    search: {
      provider: "local"
    }
  }
})
