<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scheme-light dark:scheme-dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'Request Insurance')</title>

    <script>
        const colorScheme = window.matchMedia('(prefers-color-scheme: dark)');
        const applyColorScheme = event => document.documentElement.classList.toggle('dark', event.matches);
        applyColorScheme(colorScheme);
        colorScheme.addEventListener('change', applyColorScheme);
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss-browser/4.3.2/index.global.min.js" integrity="sha512-wf0Z7EtuTFwP09V3hWIeyfzBKDlqjvwls1kjiGH8F2xB6hwD04IXyOyCADqlIyr+HtwgAq7CtPMll7eI49Ab8Q==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style type="text/tailwindcss">
        @custom-variant dark (&:where(.dark, .dark *));

        @theme {
            --color-canvas: #f4f7fb;
            --color-ink: #172033;
            --color-insurance: #4f46e5;
            --color-terminal: #101828;
            --font-sans: "Aptos", "Inter", ui-sans-serif, system-ui, sans-serif;
            --font-mono: "Berkeley Mono", "Cascadia Code", "SFMono-Regular", Consolas, monospace;
        }

        @layer base {
            * {
                border-color: var(--color-slate-200);
            }

            body {
                @apply bg-canvas text-ink antialiased dark:bg-slate-950 dark:text-slate-100;
            }

            button, a, input, select {
                @apply focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-insurance;
            }
        }

        @media (prefers-color-scheme: dark) {
            * {
                border-color: var(--color-slate-800);
            }
        }

        @layer components {
            /* State chip; suffix matches State::getBootstrapColor / HttpCode::badgeColor. */
            .chip {
                @apply inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset;
            }
            .chip-secondary { @apply bg-slate-100 text-slate-600 ring-slate-500/20 dark:bg-slate-800 dark:text-slate-300; }
            .chip-info      { @apply bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-950/50 dark:text-blue-300; }
            .chip-success   { @apply bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-950/50 dark:text-emerald-300; }
            .chip-danger    { @apply bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-950/50 dark:text-red-300; }
            .chip-warning   { @apply bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-950/50 dark:text-amber-300; }
            .chip-primary   { @apply bg-indigo-50 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-950/50 dark:text-indigo-300; }

            /* Text tone for the lifecycle-strip counters; dots piggyback via bg-current. */
            .tone-secondary { @apply text-slate-500 dark:text-slate-400; }
            .tone-info      { @apply text-blue-600 dark:text-blue-400; }
            .tone-success   { @apply text-emerald-600 dark:text-emerald-400; }
            .tone-danger    { @apply text-red-600 dark:text-red-400; }
            .tone-warning   { @apply text-amber-600 dark:text-amber-400; }
            .tone-primary   { @apply text-indigo-600 dark:text-indigo-400; }

            /* Row action button: consistent size so column slots stay aligned. */
            .act {
                @apply inline-flex h-7 w-full cursor-pointer items-center justify-center whitespace-nowrap rounded-lg border text-xs font-semibold transition-colors;
            }

            /* Vertical key/value tables used on the inspect pages. */
            .kv td { @apply border-b px-3 py-2.5 align-top; }
            .kv tr:last-child td { @apply border-0; }
            .kv td:first-child { @apply w-px whitespace-nowrap pr-6 font-sans text-xs font-bold uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400; }
        }
    </style>

    {{-- JSON syntax highlighting inside always-dark terminal blocks, pinned from cdnjs. --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/styles/github-dark.min.css" integrity="sha512-rO+olRTkcf304DQBxSWxln8JXCzTHlKnIdnMUwYvQa9/Jd4cQaNkItIUj6Z4nvW1dqK0SKXLbn9h4KwZTNtAyw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/highlight.min.js" integrity="sha512-EBLzUL8XLl+va/zAsmXwS7Z2B1F9HUHkZwyS/VKwh3S7T/U0nF4BaU29EP/ZSf6zgiIxYAnKLu6bJ8dqpmX5uw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/languages/json.min.js" integrity="sha512-f2/ljYb/tG4fTHu6672tyNdoyhTIpt4N1bGrBE8ZjwIgrjDCd+rljLpWCZ2Vym9PBWQy2Tl9O22Pp2rMOMvH4g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
</head>
<body class="min-h-full">
    <header class="border-b bg-white/90 backdrop-blur dark:bg-slate-950/90">
        <div class="mx-auto flex max-w-[96rem] min-[1800px]:max-w-[120rem] items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ route('request-insurances.index') }}" class="group flex items-center gap-3 rounded-lg">
                <span class="grid size-9 place-items-center rounded-xl bg-terminal text-sm font-black tracking-tight text-white shadow-sm shadow-slate-900/20 transition-transform group-hover:-rotate-3 dark:bg-white dark:text-slate-950">⇄</span>
                <span>
                    <span class="block text-sm font-bold tracking-tight">Request Insurance</span>
                    <span class="block text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Delivery pipeline</span>
                </span>
            </a>
            <a href="/" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">Back to application</a>
        </div>
    </header>

    <main class="mx-auto w-full max-w-[96rem] min-[1800px]:max-w-[120rem] px-4 py-6 sm:px-6 lg:px-8 lg:py-10">
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
        const timestampFormat = new Intl.DateTimeFormat('sv-SE', {
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit',
            timeZoneName: 'short',
        });
        function upgradeTimestamps(root) {
            (root || document).querySelectorAll('time[data-ts]').forEach(el => {
                const then = Date.parse(el.dataset.ts);
                if (isNaN(then)) return;
                // The viewer's own timezone, with the zone named; recent values also show the time since.
                const absolute = timestampFormat.format(then);
                el.title = absolute;
                const delta = Date.now() - then;
                el.textContent = Math.abs(delta) < 86400000 ? `${absolute} (${relativeTime(delta)})` : absolute;
            });
        }
        document.addEventListener('DOMContentLoaded', () => {
            if (window.hljs) hljs.highlightAll();
            upgradeTimestamps();

            document.querySelectorAll('form[data-confirm]').forEach(form => {
                form.addEventListener('submit', event => {
                    if (!window.confirm(form.dataset.confirm)) {
                        event.preventDefault();
                    }
                });
            });
        });
    </script>
    @yield('scripts')
</body>
</html>
