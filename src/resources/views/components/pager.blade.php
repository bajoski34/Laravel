@if ($page && ($page['total_pages'] ?? 1) > 1)
    <nav class="flw-pager" aria-label="Pages">
        @if ($page['current_page'] > 1)
            <a class="flw-btn flw-btn--outline flw-btn--sm" href="{{ $urlWith(['page' => $page['current_page'] - 1]) }}">Previous</a>
        @endif
        <span class="flw-muted">Page {{ $page['current_page'] }} of {{ $page['total_pages'] }}</span>
        @if ($page['current_page'] < $page['total_pages'])
            <a class="flw-btn flw-btn--outline flw-btn--sm" href="{{ $urlWith(['page' => $page['current_page'] + 1]) }}">Next</a>
        @endif
    </nav>
@endif
