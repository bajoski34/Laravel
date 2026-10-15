// One Shiki highlighter for every interactive tool, loaded on first use.
// It uses the same themes as the site's Markdown code blocks (github-light and
// github-dark), and emits the --shiki-light / --shiki-dark variables VitePress
// already styles, so tool output is indistinguishable from static code blocks.
import type { HighlighterCore } from 'shiki/core'

export type CodeLang = 'php' | 'shellscript' | 'dotenv' | 'tsx' | 'vue' | 'svelte'

let highlighter: Promise<HighlighterCore> | null = null

function load(): Promise<HighlighterCore> {
  highlighter ??= (async () => {
    const [{ createHighlighterCore }, { createJavaScriptRegexEngine }] = await Promise.all([
      import('shiki/core'),
      import('shiki/engine/javascript'),
    ])

    return createHighlighterCore({
      themes: [import('shiki/themes/github-light.mjs'), import('shiki/themes/github-dark.mjs')],
      langs: [
        import('shiki/langs/php.mjs'),
        import('shiki/langs/shellscript.mjs'),
        import('shiki/langs/dotenv.mjs'),
        import('shiki/langs/tsx.mjs'),
        import('shiki/langs/vue.mjs'),
        import('shiki/langs/svelte.mjs'),
      ],
      engine: createJavaScriptRegexEngine(),
    })
  })()

  return highlighter
}

/** Highlighted <pre> markup in VitePress's dual-theme format. */
export async function highlight(code: string, lang: CodeLang): Promise<string> {
  const shiki = await load()

  return shiki.codeToHtml(code, {
    lang,
    themes: { light: 'github-light', dark: 'github-dark' },
    defaultColor: false,
    transformers: [
      {
        pre(node) {
          this.addClassToHast(node, 'vp-code')
        },
      },
    ],
  })
}

export function escapeHtml(text: string): string {
  return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}
