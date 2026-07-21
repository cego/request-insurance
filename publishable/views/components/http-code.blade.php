@if($httpCode)
    <span class="chip chip-{{ $badgeColor ?: 'secondary' }} font-mono tabular-nums">{{ $httpCode }}</span>
@else
    <span class="font-mono text-slate-400 dark:text-slate-500" title="No response yet">—</span>
@endif
