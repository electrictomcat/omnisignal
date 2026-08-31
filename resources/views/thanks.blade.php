<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanks for your purchase &bull; OmniSignal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#090D16] text-slate-200 antialiased selection:bg-emerald-500/20 selection:text-emerald-300">

    <div class="fixed inset-0 radial-zen pointer-events-none -z-10"></div>

    <!-- Header -->
    <header class="sticky top-0 z-50 backdrop-blur-md bg-[#090D16]/90 border-b border-slate-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="/" class="flex items-center space-x-3 group">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-500/20 via-cyan-500/20 to-indigo-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400 font-bold text-lg group-hover:border-emerald-400/60 transition shadow-lg">
                    ॐ
                </div>
                <span class="text-lg font-bold tracking-tight text-white flex items-center gap-1.5">
                    OmniSignal
                </span>
            </a>

            <div class="flex items-center space-x-5 text-sm font-medium">
                <a href="/" class="text-slate-400 hover:text-white transition">Home</a>
                <a href="/portal" class="text-slate-400 hover:text-white transition">License Portal</a>
                <a href="/docs" class="text-slate-400 hover:text-white transition">Docs</a>
            </div>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-20">

        <div class="text-center">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 text-xs font-medium text-emerald-400 mb-4 border border-emerald-500/20">
                <span>Payment received</span>
            </div>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-white">Thanks &mdash; you're all set</h1>
            <p class="mt-4 text-slate-400 text-base max-w-xl mx-auto">
                Your licence key is on its way to the email address you checked out with. Receipts and invoices come from Lemon Squeezy, our merchant of record.
            </p>
        </div>

        <div class="mt-12 rounded-2xl bg-slate-900/80 border border-slate-800 p-6 sm:p-8 shadow-2xl">
            <h2 class="text-sm font-bold text-slate-300 uppercase tracking-wider font-mono mb-5">Next steps</h2>
            <ol class="space-y-5 text-sm text-slate-300">
                <li class="flex gap-4">
                    <span class="shrink-0 w-7 h-7 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 font-mono text-xs flex items-center justify-center">1</span>
                    <div>
                        <strong class="text-white block">Find your licence key</strong>
                        <span class="text-slate-400">Check your inbox, or <a href="{{ route('portal') }}" class="text-emerald-400 hover:underline">open the licence portal</a> and we'll email you a secure link.</span>
                    </div>
                </li>
                <li class="flex gap-4">
                    <span class="shrink-0 w-7 h-7 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 font-mono text-xs flex items-center justify-center">2</span>
                    <div>
                        <strong class="text-white block">Install it</strong>
                        <span class="text-slate-400">Follow the <a href="{{ route('docs') }}" class="text-emerald-400 hover:underline">setup guide</a> for Laravel, WordPress or the PHP SDK.</span>
                    </div>
                </li>
                <li class="flex gap-4">
                    <span class="shrink-0 w-7 h-7 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 font-mono text-xs flex items-center justify-center">3</span>
                    <div>
                        <strong class="text-white block">Activate your domain</strong>
                        <span class="text-slate-400">Paste the key into the plugin settings or your <code class="text-slate-300">.env</code>, and it registers the site against your licence.</span>
                    </div>
                </li>
            </ol>
        </div>

        <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <a href="{{ route('portal') }}" class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800 hover:border-emerald-500/40 transition">
                <strong class="text-white block">Manage your licence</strong>
                <span class="text-slate-400 text-xs">View keys, see activated domains, free one up.</span>
            </a>
            <a href="{{ route('refunds') }}" class="p-5 rounded-2xl bg-slate-900/60 border border-slate-800 hover:border-emerald-500/40 transition">
                <strong class="text-white block">Changed your mind?</strong>
                <span class="text-slate-400 text-xs">14-day money-back guarantee, no questions asked.</span>
            </a>
        </div>

        <p class="mt-10 text-center text-xs text-slate-500">
            Something not right? Email <a href="mailto:support@omnisignal.dev" class="text-emerald-400 hover:underline">support@omnisignal.dev</a> and we'll sort it out.
        </p>

    </main>

    <!-- Footer -->
    <footer class="py-12 border-t border-slate-800/60 text-center text-xs text-slate-500 mt-20">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center space-x-2">
                <span class="text-emerald-400">🕉️</span>
                <span class="font-bold text-slate-300">OmniSignal</span>
                <span>&bull; Pure Signal. Zero Noise.</span>
            </div>
            <div class="flex items-center space-x-6">
                <a href="/portal" class="hover:text-slate-300 transition">Customer Portal</a>
                <a href="/refunds" class="hover:text-slate-300 transition">Refunds</a>
                <a href="/terms" class="hover:text-slate-300 transition">Terms</a>
                <a href="/privacy" class="hover:text-slate-300 transition">Privacy</a>
            </div>
            <p>&copy; 2026 OmniSignal (<a href="https://omnisignal.dev" class="text-slate-400 hover:underline">omnisignal.dev</a>). All rights reserved.</p>
        </div>
    </footer>

</body>
</html>
