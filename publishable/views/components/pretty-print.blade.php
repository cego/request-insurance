@if(filled($content))
    <pre class="overflow-x-auto whitespace-pre-wrap break-words rounded-xl bg-terminal text-xs leading-5 [&>code]:!bg-transparent [&>code]:!p-3 [&>code]:text-slate-200"><code class="json">{{ $content }}</code></pre>
@else
    <span class="text-slate-400 dark:text-slate-500">—</span>
@endif
