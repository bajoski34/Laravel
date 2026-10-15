@once
<style>
    :where(.flw-admin) { display: grid; gap: .75rem; font-family: inherit; color: inherit; }
    :where(.flw-admin .flw-card) { display: grid; gap: .75rem; padding: 1rem; border: 1px solid #e5e7eb; border-radius: .75rem; }
    :where(.flw-admin .flw-row) { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: .5rem; }
    :where(.flw-admin .flw-amount) { font-size: 1.125rem; font-weight: 600; font-variant-numeric: tabular-nums; }
    :where(.flw-admin .flw-muted) { font-size: .8rem; color: #6b7280; }
    :where(.flw-admin .flw-pill) { font-size: .75rem; font-weight: 500; padding: .125rem .6rem; border-radius: 999px; background: #f3f4f6; color: #374151; }
    :where(.flw-admin .flw-pill[data-tone="attention"]) { background: #fef3c7; color: #78350f; }
    :where(.flw-admin .flw-pill[data-tone="info"]) { background: #e0f2fe; color: #0c4a6e; }
    :where(.flw-admin .flw-pill[data-tone="good"]) { background: #dcfce7; color: #14532d; }
    :where(.flw-admin .flw-pill[data-tone="bad"]) { background: #fee2e2; color: #7f1d1d; }
    :where(.flw-admin .flw-urgent) { color: #b91c1c; font-weight: 500; }
    :where(.flw-admin .flw-error) { color: #b91c1c; font-size: .875rem; }
    :where(.flw-admin .flw-ok) { color: #15803d; font-size: .875rem; font-weight: 500; }
    :where(.flw-admin form) { display: grid; gap: .75rem; padding: .75rem; border-radius: .5rem; background: #f9fafb; }
    :where(.flw-admin label) { display: grid; gap: .25rem; font-size: .875rem; }
    :where(.flw-admin input:not([type=checkbox]):not([type=file]), .flw-admin select, .flw-admin textarea) { font: inherit; font-size: .875rem; padding: .4rem .5rem; border: 1px solid #d1d5db; border-radius: .375rem; background: #fff; color: inherit; }
    :where(.flw-admin .flw-check) { display: flex; gap: .5rem; align-items: flex-start; }
    :where(.flw-admin button, .flw-admin .flw-button) { font: inherit; font-size: .875rem; font-weight: 500; padding: .45rem .9rem; border-radius: .375rem; border: 0; background: #111827; color: #fff; cursor: pointer; width: fit-content; text-decoration: none; }
    :where(.flw-admin summary) { cursor: pointer; font-size: .875rem; }
    :where(.flw-admin dl) { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; margin: 0; }
    :where(.flw-admin dd) { margin: 0; font-weight: 600; font-variant-numeric: tabular-nums; }
    :where(.flw-admin ol) { display: grid; gap: .35rem; margin: .5rem 0 0; padding-left: 1rem; font-size: .875rem; }
    @media (prefers-color-scheme: dark) {
        :where(.flw-admin .flw-card) { border-color: #262626; }
        :where(.flw-admin form) { background: #171717; }
        :where(.flw-admin input:not([type=checkbox]):not([type=file]), .flw-admin select, .flw-admin textarea) { background: #0a0a0a; border-color: #404040; }
        :where(.flw-admin .flw-muted) { color: #a3a3a3; }
        :where(.flw-admin .flw-pill) { background: #262626; color: #d4d4d4; }
        :where(.flw-admin button, .flw-admin .flw-button) { background: #fafafa; color: #111827; }
    }
</style>
@endonce
