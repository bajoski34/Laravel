<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { firstError, formatMoney, fxStartOverUrl } from './admin';
    import { formatDuration, startCountdown, startPolling } from './format';
    import type { FlutterwaveErrors, FlutterwaveFxPanel } from './types';

    interface Props {
        /** From fx()->panel($request->query('fx_quote'), $request->query('fx_trade')) on the server. */
        panel: FlutterwaveFxPanel;
        /** The prop name you passed the panel as, so checking for updates only reloads it. Defaults to all props. */
        reloadOnly?: string[];
        /** How often to check a pending quote or trade, in milliseconds. */
        pollInterval?: number;
        /** Replaces the default classes. */
        class?: string;
    }

    let { panel, reloadOnly, pollInterval = 3000, class: className }: Props = $props();

    const primary = 'rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700 disabled:opacity-50 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200';
    const quiet = 'text-sm text-neutral-600 underline-offset-2 hover:underline dark:text-neutral-300';
    const input = 'rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-950';

    const quote = $derived(panel.quote);
    const trade = $derived(panel.trade);
    const waiting = $derived(Boolean(trade?.pending || (!trade && quote?.pending)));

    // Request step
    let pair = $state('');
    let quantity = $state('');
    const selected = $derived(pair || (panel.pairs[0]?.join('/') ?? 'NGN/USD'));
    const base = $derived(selected.split('/')[0]);
    const target = $derived(selected.split('/')[1]);

    // Quote step
    let narration = $state('');
    let confirming = $state(false);
    let error = $state<string | null>(null);
    let busy = $state(false);
    let ticked = $state<number | null>(null);
    const secondsLeft = $derived(ticked ?? quote?.expires_in ?? null);

    const expired = $derived(quote?.status === 'EXPIRED' || (quote?.status === 'READY' && secondsLeft === 0));
    const sell = $derived(formatMoney(quote?.base ?? null, quote?.approved_quantity ?? null));
    const get = $derived(formatMoney(quote?.target ?? null, quote?.total_value ?? null));
    const trimmed = $derived(quote?.quantity != null && quote?.approved_quantity != null && quote.approved_quantity < quote.quantity);

    $effect(() => {
        const expiresIn = quote?.expires_in;
        void quote?.id; // restart the countdown for a new quote
        ticked = null;

        if (expiresIn != null) {
            return startCountdown(expiresIn, (left) => (ticked = left));
        }
    });

    $effect(() => {
        if (!waiting || !pollInterval) {
            return;
        }

        const only = reloadOnly;

        return startPolling(() => router.reload(only ? { only } : {}), pollInterval);
    });

    function startOver() {
        router.visit(fxStartOverUrl());
    }

    function requestQuote(event: SubmitEvent) {
        event.preventDefault();

        if (!(Number(quantity) > 0)) {
            error = `Enter how much ${base} to sell.`;

            return;
        }

        busy = true;
        error = null;
        router.post(panel.urls!.quote, { base, target, quantity }, {
            preserveScroll: true,
            onError: (errors) => (error = firstError(errors as FlutterwaveErrors)),
            onFinish: () => (busy = false),
        });
    }

    function tradeQuote(event: SubmitEvent) {
        event.preventDefault();

        if (!narration.trim()) {
            error = 'Add a note for your records, e.g. Supplier payment.';

            return;
        }

        if (!confirming) {
            error = null;
            confirming = true;

            return;
        }

        busy = true;
        router.post(panel.urls!.trade, { quote_id: quote!.id, narration }, {
            preserveScroll: true,
            onError: (errors) => {
                error = firstError(errors as FlutterwaveErrors);
                confirming = false;
            },
            onFinish: () => (busy = false),
        });
    }
</script>

<div class={className ?? 'grid max-w-md gap-4 rounded-xl border border-neutral-200 p-5 dark:border-neutral-800'}>
    {#if panel.error}
        <p role="alert" class="text-sm text-red-700 dark:text-red-400">{panel.error}</p>
    {/if}

    {#if trade}
        <div class="grid gap-3" aria-live="polite">
            <h3 class="text-base font-semibold">{trade.status === 'SETTLED' ? 'Trade settled' : trade.status === 'FAILED' ? 'Trade failed' : 'Trade in progress…'}</h3>
            <p class="text-sm {trade.status === 'FAILED' ? 'text-red-700 dark:text-red-400' : 'text-neutral-600 dark:text-neutral-300'}">
                {#if trade.status === 'SETTLED'}
                    Sold {formatMoney(trade.base, trade.sold)} for {formatMoney(trade.target, trade.bought)}{trade.recipient ? `, paid to ${trade.recipient}` : ''}.
                {:else if trade.status === 'FAILED'}
                    {trade.message ?? 'Flutterwave couldn’t complete the trade.'}
                {:else}
                    Selling {formatMoney(trade.base, trade.sold)} for {formatMoney(trade.target, trade.bought)}. This updates by itself.
                {/if}
            </p>
            <p class="text-xs text-neutral-500 dark:text-neutral-400">Reference {trade.reference ?? trade.id}</p>
            {#if !trade.pending}
                <button type="button" class="{primary} w-fit" onclick={startOver}>Start another conversion</button>
            {/if}
        </div>
    {:else if quote && quote.pending}
        <div class="grid gap-2" aria-live="polite">
            <h3 class="text-base font-semibold">Getting a rate…</h3>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">Flutterwave usually prices a quote within a few seconds. This updates by itself.</p>
        </div>
    {:else if quote && (!quote.tradable || expired)}
        <div class="grid gap-3" aria-live="polite">
            <h3 class="text-base font-semibold">{expired ? 'This rate has expired' : 'No rate available'}</h3>
            <p class="text-sm text-neutral-600 dark:text-neutral-300">{expired ? 'Rates are held for 5 minutes. Get a new one to continue.' : quote.message}</p>
            <button type="button" class="{primary} w-fit" onclick={startOver}>Get a new rate</button>
        </div>
    {:else if quote}
        <form class="grid gap-4" onsubmit={tradeQuote}>
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="text-base font-semibold">Your rate</h3>
                {#if secondsLeft !== null}
                    <span class="text-sm tabular-nums text-neutral-500 dark:text-neutral-400">Held for {formatDuration(secondsLeft)}</span>
                {/if}
            </div>
            <dl class="grid grid-cols-2 gap-3">
                <div><dt class="text-xs text-neutral-500 dark:text-neutral-400">You sell</dt><dd class="font-semibold tabular-nums">{sell}</dd></div>
                <div><dt class="text-xs text-neutral-500 dark:text-neutral-400">You get</dt><dd class="font-semibold tabular-nums">{get}</dd></div>
                <div class="col-span-2"><dt class="text-xs text-neutral-500 dark:text-neutral-400">Rate</dt><dd class="tabular-nums">1 {quote.base} = {quote.rate} {quote.target}</dd></div>
            </dl>
            {#if trimmed}
                <p class="rounded-md bg-amber-50 p-2 text-sm text-amber-900 dark:bg-amber-400/10 dark:text-amber-300">Flutterwave approved {sell} of the {formatMoney(quote.base, quote.quantity)} you asked for.</p>
            {/if}
            {#if panel.urls}
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Note for your records</span>
                    <input bind:value={narration} placeholder="Supplier payment" class={input} oninput={() => (confirming = false)} />
                </label>
                {#if error}
                    <p role="alert" class="text-sm text-red-700 dark:text-red-400">{error}</p>
                {/if}
                {#if confirming}
                    <p class="text-sm font-medium">Sell {sell} for {get}? Trades can’t be undone.</p>
                {/if}
                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" disabled={busy} class={primary}>{busy ? 'Trading…' : confirming ? 'Confirm trade' : 'Trade at this rate'}</button>
                    <button type="button" class={quiet} onclick={() => (confirming ? (confirming = false) : startOver())}>{confirming ? 'Back' : 'Get a different rate'}</button>
                </div>
            {/if}
        </form>
    {:else if !panel.urls}
        <p class="text-sm text-neutral-500 dark:text-neutral-400">Add Route::flutterwaveAdmin() to your routes to request quotes here.</p>
    {:else}
        <form class="grid gap-4" onsubmit={requestQuote}>
            <div class="grid gap-1">
                <h3 class="text-base font-semibold">Convert currency</h3>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Get a rate, then trade it within 5 minutes. Weekdays only, from USD 1,000 or its equivalent.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-[auto_1fr]">
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Convert</span>
                    <select value={selected} onchange={(event) => (pair = event.currentTarget.value)} class={input}>
                        {#each panel.pairs as [from, to] (`${from}/${to}`)}
                            <option value="{from}/{to}">{from} to {to}</option>
                        {/each}
                    </select>
                </label>
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Amount of {base} to sell</span>
                    <input bind:value={quantity} inputmode="decimal" placeholder="2000000" class="{input} tabular-nums" />
                </label>
            </div>
            {#if error}
                <p role="alert" class="text-sm text-red-700 dark:text-red-400">{error}</p>
            {/if}
            <button type="submit" disabled={busy} class="{primary} w-fit">{busy ? 'Requesting…' : `Get a ${base} to ${target} rate`}</button>
        </form>
    {/if}
</div>
