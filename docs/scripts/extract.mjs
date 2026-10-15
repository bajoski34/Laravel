// Reads the package source and writes the data behind the Services and Artisan reference pages.
// Runs before every dev/build, so the docs always match the code in src/.
import { readFileSync, readdirSync, writeFileSync, mkdirSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..')
const src = join(root, 'src')
const out = join(root, 'docs', '.vitepress', 'data')
mkdirSync(out, { recursive: true })

/** Text of a /** ... *\/ block, without @tags. */
function cleanDoc(block) {
  if (!block) return ''
  return block
    .replace(/^\/\*\*|\*\/$/g, '')
    .split('\n')
    .map((l) => l.replace(/^\s*\*\s?/, ''))
    .filter((l) => !l.trim().startsWith('@'))
    .join('\n')
    .trim()
}

/** Split a docblock into prose and an indented example, when it has one. */
function splitExample(text) {
  const lines = text.split('\n')
  const prose = []
  const example = []
  for (const line of lines) {
    if (/^ {4,}\S/.test(line) || /^Flutterwave::/.test(line.trim())) example.push(line.replace(/^ {4}/, ''))
    else prose.push(line)
  }
  return { summary: prose.join(' ').replace(/\s+/g, ' ').trim(), example: example.join('\n').trim() }
}

// ---------------------------------------------------------------- services

const facade = readFileSync(join(src, 'Flutterwave.php'), 'utf8')
const map = Object.fromEntries(
  [...facade.matchAll(/'(\w+)' => (\w+)::class/g)].map(([, key, cls]) => [cls, key]),
)
const accessors = Object.fromEntries(
  [...facade.matchAll(/public function (\w+)\(\): (\w+)\s*\{\s*return \$this->use\('(\w+)'\)/g)].map(([, method, , key]) => [key, method]),
)

const services = []
for (const file of readdirSync(join(src, 'Services')).sort()) {
  const cls = file.replace(/\.php$/, '')
  const key = map[cls]
  if (!key) continue

  const code = readFileSync(join(src, 'Services', file), 'utf8')
  const classDoc = cleanDoc(code.match(/(\/\*\*(?:(?!\*\/)[\s\S])*\*\/)\s*(?:final\s+)?class\s/)?.[1])

  const methods = []
  const re = /(\/\*\*(?:(?!\*\/)[\s\S])*\*\/)?\s*public (static )?function (\w+)\(([^)]*)\)(?::\s*([^\s{]+))?/g
  for (const m of code.matchAll(re)) {
    const [, doc, isStatic, name, params, returns] = m
    if (name.startsWith('__')) continue
    const { summary, example } = splitExample(cleanDoc(doc))
    methods.push({
      name,
      static: Boolean(isStatic),
      params: params.replace(/\s+/g, ' ').trim(),
      returns: returns ?? '',
      summary,
      example,
    })
  }

  services.push({
    key,
    class: cls,
    accessor: accessors[key] ?? null,
    summary: splitExample(classDoc).summary,
    methods,
  })
}

writeFileSync(join(out, 'services.json'), JSON.stringify(services, null, 2))

// ---------------------------------------------------------------- artisan

const commands = []
for (const file of readdirSync(join(src, 'Console')).filter((f) => f.endsWith('Command.php')).sort()) {
  const code = readFileSync(join(src, 'Console', file), 'utf8')
  const sig = code.match(/\$signature = '([\s\S]*?)';/)?.[1]
  const description = code.match(/\$description = '([^']*)'/)?.[1] ?? ''
  if (!sig) continue

  const name = sig.trim().split(/\s/)[0]
  const args = []
  const options = []
  for (const [, raw] of sig.matchAll(/\{([^}]*)\}/g)) {
    const [def, help = ''] = raw.split(/\s:\s/)
    const d = def.trim()
    const entry = { help: help.trim().replace(/\\'/g, "'") }
    if (d.startsWith('--')) {
      const [flag, value] = d.slice(2).split('=')
      options.push({ ...entry, name: flag, takesValue: d.includes('='), default: value ?? '' })
    } else {
      const optional = d.includes('?') || d.includes('=')
      const [argName, value] = d.replace('?', '').split('=')
      args.push({ ...entry, name: argName, optional, default: value ?? '' })
    }
  }
  commands.push({ name, description: description.replace(/\\'/g, "'"), args, options })
}

writeFileSync(join(out, 'artisan.json'), JSON.stringify(commands, null, 2))

// ---------------------------------------------------------------- markdown includes (indexed by site search)

const md = (s) => s.replace(/\|/g, '\\|').replace(/</g, '&lt;')

const artisanMd = commands
  .map((c) => {
    const usage = [
      'php artisan',
      c.name,
      ...c.args.map((a) => (a.optional ? `[${a.name}]` : `<${a.name}>`)),
      ...(c.options.length ? ['[options]'] : []),
    ].join(' ')
    const rows = [
      ...c.args.map((a) => `| \`${a.name}\`${a.optional ? '' : ' *'} | ${md(a.help)}${a.default ? ` Default \`${a.default}\`.` : ''} |`),
      ...c.options.map((o) => `| \`--${o.name}${o.takesValue ? '=' : ''}\` | ${md(o.help)}${o.default ? ` Default \`${o.default}\`.` : ''} |`),
    ]
    return [
      `### ${c.name}`,
      '',
      `${c.description}.`,
      '',
      '```bash',
      usage,
      '```',
      ...(rows.length ? ['', '| Argument / option | Description |', '| --- | --- |', ...rows] : []),
    ].join('\n')
  })
  .join('\n\n')

writeFileSync(join(out, 'artisan.md'), artisanMd + '\n')

const servicesMd = [
  '| Accessor | Class | Methods |',
  '| --- | --- | --- |',
  ...services.map(
    (s) =>
      `| \`${s.accessor ? `Flutterwave::${s.accessor}()` : `Flutterwave::use('${s.key}')`}\` | ${s.class} | ${s.methods.map((m) => `\`${m.name}\``).join(', ')} |`,
  ),
].join('\n')

writeFileSync(join(out, 'services.md'), servicesMd + '\n')

console.log(`docs data: ${services.length} services, ${services.reduce((n, s) => n + s.methods.length, 0)} methods, ${commands.length} artisan commands`)
