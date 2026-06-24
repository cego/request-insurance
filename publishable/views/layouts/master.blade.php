<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Request Insurance</title>

    {{-- Theme tokens. Light by default, swapped automatically by the OS preference. --}}
    <style>
        :root{
            --bg:#EEF1F6; --surface:#FFFFFF; --surface-2:#F6F8FB; --line:#DCE2EC;
            --ink:#16202C; --ink-soft:#5A6678; --accent:#3D4EDB;
            --c-secondary:#5B6B7F; --c-info:#0E8FA8; --c-success:#1F9D57;
            --c-danger:#DC3D43; --c-warning:#B5670F; --c-primary:var(--accent);
        }
        @media (prefers-color-scheme: dark){
            :root{
                --bg:#0E141B; --surface:#161E29; --surface-2:#1C2735; --line:#2A3645;
                --ink:#E6EBF2; --ink-soft:#92A1B5; --accent:#7C86FF;
                --c-secondary:#9FB0C4; --c-info:#36C6DE; --c-success:#41C97A;
                --c-danger:#FF6B70; --c-warning:#E0A24A; --c-primary:var(--accent);
            }
        }
        html{color-scheme:light dark;}
        pre{white-space:pre-wrap; word-break:break-word; margin:0;}
    </style>

    {{-- Modern Tailwind v4, in-page JIT, pinned from cdnjs (no build step). --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss-browser/4.1.13/index.global.min.js" integrity="sha512-TscjjxDy2iXx5s55Ar78c01JDHUug0K5aw4YKId9Yuocjx3ueX/X9PFyH5XNRVWqagx3TtcQWQVBaHAIPFjiFA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style type="text/tailwindcss">
        @theme inline{
            --color-bg:var(--bg); --color-surface:var(--surface); --color-surface-2:var(--surface-2);
            --color-line:var(--line); --color-ink:var(--ink); --color-ink-soft:var(--ink-soft);
            --color-accent:var(--accent);
            --color-st-secondary:var(--c-secondary); --color-st-info:var(--c-info);
            --color-st-success:var(--c-success); --color-st-danger:var(--c-danger);
            --color-st-warning:var(--c-warning); --color-st-primary:var(--c-primary);
            --font-sans:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
            --font-mono:ui-monospace,"SF Mono","JetBrains Mono","Cascadia Code",Menlo,Consolas,monospace;
        }
        /* State chip: tinted background + colored text/dot, theme-aware via color-mix. Set --chip per state. */
        .chip{ background:color-mix(in srgb, var(--chip,var(--accent)) 13%, transparent); color:var(--chip,var(--accent)); }
        @media (prefers-color-scheme: dark){ .chip{ background:color-mix(in srgb, var(--chip,var(--accent)) 20%, transparent); } }
        /* Row action button: clearly an action, consistent size so column slots stay aligned. */
        .act{ display:inline-flex; align-items:center; justify-content:center; width:100%; height:1.75rem;
              border-radius:.375rem; border:1px solid; font-size:12px; font-weight:500; cursor:pointer; white-space:nowrap; }
        /* Vertical key/value tables used on the inspect pages. */
        .kv td{ padding:.4rem .75rem; vertical-align:top; border-bottom:1px solid color-mix(in srgb, var(--line) 70%, transparent); }
        .kv tr:last-child td{ border-bottom:0; }
        .kv td:first-child{ width:1%; white-space:nowrap; color:var(--ink-soft); font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
    </style>

    {{-- JSON syntax highlighting, pinned from cdnjs, light/dark themes swapped by OS preference. --}}
    <link rel="stylesheet" media="(prefers-color-scheme: light)" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/styles/github.min.css" integrity="sha512-0aPQyyeZrWj9sCA46UlmWgKOP0mUipLQ6OZXu8l4IcAmD2u31EPEy9VcIMvl7SoAaKe8bLXZhYoMaE/in+gcgA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" media="(prefers-color-scheme: dark)" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/styles/github-dark.min.css" integrity="sha512-rO+olRTkcf304DQBxSWxln8JXCzTHlKnIdnMUwYvQa9/Jd4cQaNkItIUj6Z4nvW1dqK0SKXLbn9h4KwZTNtAyw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/highlight.min.js" integrity="sha512-EBLzUL8XLl+va/zAsmXwS7Z2B1F9HUHkZwyS/VKwh3S7T/U0nF4BaU29EP/ZSf6zgiIxYAnKLu6bJ8dqpmX5uw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/languages/json.min.js" integrity="sha512-f2/ljYb/tG4fTHu6672tyNdoyhTIpt4N1bGrBE8ZjwIgrjDCd+rljLpWCZ2Vym9PBWQy2Tl9O22Pp2rMOMvH4g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
</head>
<body class="bg-bg text-ink font-sans antialiased min-h-screen">
    <header class="sticky top-0 z-20 border-b border-line bg-surface/85 backdrop-blur">
        <div class="mx-auto max-w-[1500px] px-6 h-14 flex items-center gap-4">
            <a href="{{ route('request-insurances.index') }}" class="flex items-center gap-2.5 no-underline text-ink">
                <span class="grid place-items-center size-6 rounded-[7px] bg-accent text-white text-[13px] font-bold">R</span>
                <span class="font-mono text-[15px] font-semibold tracking-tight">request<span class="text-ink-soft">·</span>insurance</span>
            </a>
        </div>
    </header>

    <main class="mx-auto max-w-[1500px] px-6 py-8">
        @yield('content')
    </main>

    <script>
        // Format timestamps: show a relative duration when recent, keep the absolute value on hover.
        function relativeTime(deltaMs) {
            const abs = Math.abs(deltaMs);
            const s = Math.round(abs / 1000), m = Math.round(s / 60), h = Math.round(m / 60);
            const t = s < 60 ? s + 's' : m < 60 ? m + 'm' : h + 'h';
            return deltaMs >= 0 ? t + ' ago' : 'in ' + t;
        }
        function upgradeTimestamps(root) {
            (root || document).querySelectorAll('time[data-ts]').forEach(el => {
                const then = Date.parse(el.dataset.ts);
                if (isNaN(then)) return;
                const delta = Date.now() - then;
                if (Math.abs(delta) < 86400000) el.textContent = relativeTime(delta); // within 24h
            });
        }
        document.addEventListener('DOMContentLoaded', () => {
            if (window.hljs) hljs.highlightAll();
            upgradeTimestamps();
        });
    </script>
    @yield('scripts')
</body>
</html>
