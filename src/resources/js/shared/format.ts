/** 1:02:05 or 4:09 */
export function formatDuration(seconds: number): string {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const rest = String(seconds % 60).padStart(2, '0');

    return hours > 0 ? `${hours}:${String(minutes).padStart(2, '0')}:${rest}` : `${minutes}:${rest}`;
}

export function formatAmount(amount: number): string {
    return amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/**
 * Tick down from expires_in, the seconds left when the page was rendered, so a wrong
 * clock on the customer's device doesn't matter. Calls onTick right away, then every second.
 */
export function startCountdown(expiresIn: number, onTick: (secondsLeft: number) => void): () => void {
    const deadline = Date.now() + expiresIn * 1000;
    const tick = () => {
        const left = Math.max(0, Math.round((deadline - Date.now()) / 1000));
        onTick(left);

        if (left === 0) {
            window.clearInterval(timer);
        }
    };
    const timer = window.setInterval(tick, 1000);
    tick();

    return () => window.clearInterval(timer);
}

/** Reload Inertia props on an interval while the tab is visible. */
export function startPolling(reload: () => void, interval: number): () => void {
    const timer = window.setInterval(() => {
        if (!document.hidden) {
            reload();
        }
    }, interval);

    return () => window.clearInterval(timer);
}

/**
 * Copy text to the clipboard. Browsers refuse on plain http:// and in some embedded
 * views; then select the fallback element's text so the customer can copy it by hand.
 */
export async function copyText(text: string, fallback?: Element | null): Promise<boolean> {
    try {
        await navigator.clipboard.writeText(text);

        return true;
    } catch {
        if (fallback) {
            const range = document.createRange();
            range.selectNodeContents(fallback);
            window.getSelection()?.removeAllRanges();
            window.getSelection()?.addRange(range);
        }

        return false;
    }
}
