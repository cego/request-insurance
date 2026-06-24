<textarea name="{{ $name }}" @disabled($disabled)
    class="w-full rounded-lg border border-line bg-surface-2 p-3 font-mono text-[12px] focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/25 disabled:opacity-70"
    oninput="this.style.overflow='hidden';this.style.height=0;this.style.height=this.scrollHeight+'px'"
    onfocus="this.style.overflow='hidden';this.style.height=0;this.style.height=this.scrollHeight+'px'">{{ $content }}</textarea>
