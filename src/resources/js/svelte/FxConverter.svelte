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
        /** Added to the wrapper. */
        class?: string;
    }

    let { panel, reloadOnly, pollInterval = 3000, class: className }: Props = $props();


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

<div class="flw flw-card flw-card--narrow{className ? ` ${className}` : ''}">
    {#if panel.error}<p role="alert" class="flw-notice flw-notice--error">{panel.error}</p>{/if}

    {#if trade}
        <div class="flw-stack" style="gap: 14px" aria-live="polite">
            <h3 class="flw-title">{trade.status === 'SETTLED' ? 'Trade settled' : trade.status === 'FAILED' ? 'Trade failed' : 'Trade in progress'}</h3>
            <hr class="flw-rule" />
            {#if trade.status === 'SETTLED'}
                <p class="flw-text">Sold {formatMoney(trade.base, trade.sold)} for <strong>{formatMoney(trade.target, trade.bought)}</strong>{trade.recipient ? `, paid to ${trade.recipient}` : ''}.</p>
            {:else if trade.status === 'FAILED'}
                <p class="flw-notice flw-notice--error">{trade.message ?? 'Flutterwave couldn’t complete the trade.'}</p>
            {:else}
                <p class="flw-countdown"><span class="flw-ring" aria-hidden="true"></span>Selling {formatMoney(trade.base, trade.sold)} for {formatMoney(trade.target, trade.bought)}. This updates by itself.</p>
            {/if}
            <p class="flw-muted">Reference {trade.reference ?? trade.id}</p>
            {#if !trade.pending}<button type="button" class="flw-btn flw-btn--primary flw-btn--block" onclick={startOver}>Start another conversion</button>{/if}
        </div>
    {:else if quote && quote.pending}
        <div class="flw-stack" style="gap: 10px" aria-live="polite">
            <h3 class="flw-title">Getting a rate</h3>
            <hr class="flw-rule" />
            <p class="flw-countdown"><span class="flw-ring" aria-hidden="true"></span>Flutterwave usually prices a quote within a few seconds. This updates by itself.</p>
        </div>
    {:else if quote && (!quote.tradable || expired)}
        <div class="flw-stack" style="gap: 14px" aria-live="polite">
            <h3 class="flw-title">{expired ? 'This rate has expired' : 'No rate available'}</h3>
            <hr class="flw-rule" />
            <p class="flw-text">{expired ? 'Rates are held for 5 minutes. Get a new one to continue.' : quote.message}</p>
            <button type="button" class="flw-btn flw-btn--primary flw-btn--block" onclick={startOver}>Get a new rate</button>
        </div>
    {:else if quote}
        <form class="flw-stack" style="gap: 16px" onsubmit={tradeQuote}>
            <div class="flw-row">
                <h3 class="flw-title">Your rate</h3>
                {#if secondsLeft !== null}<p class="flw-countdown">Held for <strong>{formatDuration(secondsLeft)}</strong></p>{/if}
            </div>
            <hr class="flw-rule" />
            <dl class="flw-dl">
                <div><dt>You sell</dt><dd>{sell}</dd></div>
                <div><dt>You get</dt><dd>{get}</dd></div>
                <div><dt>Rate</dt><dd>1 {quote.base} = {quote.rate} {quote.target}</dd></div>
            </dl>
            {#if trimmed}<p class="flw-notice flw-notice--warning">Flutterwave approved {sell} of the {formatMoney(quote.base, quote.quantity)} you asked for.</p>{/if}
            {#if panel.urls}
                <label class="flw-field{error ? ' has-error' : ''}">
                    <span class="flw-field__label">Note for your records</span>
                    <input bind:value={narration} placeholder="Supplier payment" class="flw-input" oninput={() => (confirming = false)} />
                    {#if error}<span role="alert" class="flw-field__error">{error}</span>{/if}
                </label>
                {#if confirming}<p class="flw-notice flw-notice--info">Sell {sell} for {get}? Trades can’t be undone.</p>{/if}
                <button type="submit" disabled={busy} class="flw-btn flw-btn--primary flw-btn--block">
                    {#if busy}<span class="flw-btn__spinner" aria-hidden="true"></span>{/if}
                    {busy ? 'Trading' : confirming ? 'Confirm trade' : 'Trade at this rate'}
                </button>
                <button type="button" class="flw-link-btn flw-link-btn--quiet" onclick={() => (confirming ? (confirming = false) : startOver())}>{confirming ? 'Back' : 'Get a different rate'}</button>
            {/if}
        </form>
    {:else if !panel.urls}
        <p class="flw-text">Add Route::flutterwaveAdmin() to your routes to request quotes here.</p>
    {:else}
        <form class="flw-stack" style="gap: 16px" onsubmit={requestQuote}>
            <div class="flw-stack">
                <h3 class="flw-title">Convert currency</h3>
                <p class="flw-text">Get a rate, then trade it within 5 minutes. Weekdays only, from USD 1,000 or its equivalent.</p>
            </div>
            <hr class="flw-rule" />
            <label class="flw-field">
                <span class="flw-field__label">Convert</span>
                <span class="flw-select">
                    <select value={selected} onchange={(event) => (pair = event.currentTarget.value)} class="flw-input">
                        {#each panel.pairs as [from, to] (`${from}/${to}`)}
                            <option value="{from}/{to}">{from} to {to}</option>
                        {/each}
                    </select>
                    <span class="flw-select__chevron" aria-hidden="true"><svg viewBox="0 0 16 16" width="16" height="16"><path d="m4 6 4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
                </span>
            </label>
            <label class="flw-field{error ? ' has-error' : ''}">
                <span class="flw-field__label">Amount of {base} to sell</span>
                <input bind:value={quantity} inputmode="decimal" placeholder="2000000" class="flw-input flw-num" />
                {#if error}<span role="alert" class="flw-field__error">{error}</span>{/if}
            </label>
            <button type="submit" disabled={busy} class="flw-btn flw-btn--primary flw-btn--block">
                {#if busy}<span class="flw-btn__spinner" aria-hidden="true"></span>{/if}
                {busy ? 'Requesting' : `Get a ${base} to ${target} rate`}
            </button>
        </form>
    {/if}
</div>
