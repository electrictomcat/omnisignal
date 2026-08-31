<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service &bull; OmniSignal</title>
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
                <span class="text-lg font-bold tracking-tight text-white">OmniSignal</span>
            </a>

            <div class="flex items-center space-x-5 text-sm font-medium">
                <a href="/" class="text-slate-400 hover:text-white transition">Home</a>
                <a href="/portal" class="text-slate-400 hover:text-white transition">License Portal</a>
                <a href="/docs" class="text-slate-400 hover:text-white transition">Docs</a>
            </div>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <h1 class="text-4xl font-extrabold text-white tracking-tight">Terms of Service</h1>
        <p class="mt-2 text-xs font-mono text-slate-400">Last updated: August 30, 2026</p>

        <div class="mt-8 space-y-8 text-sm text-slate-300 leading-relaxed">
            <section>
                <h2 class="text-lg font-bold text-white mb-2">1. Overview & Merchant of Record</h2>
                <p>
                    OmniSignal provides server-side offline conversion tracking software and SDKs for Laravel and WordPress. Our order process and digital commerce transactions are conducted by our online reseller & Merchant of Record, <strong>Lemon Squeezy, LLC</strong>. Lemon Squeezy provides all customer service inquiries and handles returns and sales tax compliance.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-bold text-white mb-2">2. Software Licensing & Activations</h2>
                <p>
                    Purchasing OmniSignal grants you a non-exclusive, revocable license to use the software on the number of domains specified in your tier:
                </p>
                <ul class="list-disc list-inside mt-2 space-y-1 text-slate-400">
                    <li><strong>Starter Plan:</strong> 1 active production domain.</li>
                    <li><strong>Pro Developer Plan:</strong> Up to 5 active production domains.</li>
                    <li><strong>Agency Zen Plan:</strong> Unlimited client and production domains.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-lg font-bold text-white mb-2">3. Subscriptions, Automatic Renewals & Cancellations</h2>
                <p>
                    Subscription plans automatically renew annually unless cancelled prior to the renewal date. You may cancel your subscription at any time with one click via our <a href="/portal" class="text-emerald-400 hover:underline">License Portal</a> or the Lemon Squeezy customer hub. Upon cancellation, your license remains valid until the end of your prepaid billing period.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-bold text-white mb-2">4. Refunds</h2>
                <p>
                    We offer a 14-day 100% money-back guarantee. Please review our <a href="/refunds" class="text-emerald-400 hover:underline">Refund Policy</a> for full details.
                </p>
            </section>
        </div>
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
