<textarea name="{{ $name }}" @disabled($disabled)
    class="w-full rounded-xl border bg-white p-3 font-mono text-xs text-slate-950 shadow-xs disabled:opacity-60 dark:bg-slate-950 dark:text-white"
    oninput="this.style.overflow='hidden';this.style.height=0;this.style.height=this.scrollHeight+'px'"
    onfocus="this.style.overflow='hidden';this.style.height=0;this.style.height=this.scrollHeight+'px'">{{ $content }}</textarea>
