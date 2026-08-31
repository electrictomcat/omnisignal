<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy &bull; OmniSignal</title>
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
        <h1 class="text-4xl font-extrabold text-white tracking-tight">Privacy Policy</h1>
        <p class="mt-2 text-xs font-mono text-slate-400">Last updated: August 30, 2026 &bull; Strict GDPR & DMA Compliance</p>

        <div class="mt-8 space-y-8 text-sm text-slate-300 leading-relaxed">
            <section>
                <h2 class="text-lg font-bold text-white mb-2">1. Data Minimization by Design</h2>
                <p>
                    OmniSignal is engineered on strict data minimization principles. We do not store, broker, or sell personal identifiable information (PII). All user identifiers (such as emails and phone numbers) are normalized and cryptographically hashed with SHA-256 before transmission to official ad platform APIs (Google Ads, Meta CAPI, etc.).
                </p>
            </section>

            <section>
                <h2 class="text-lg font-bold text-white mb-2">2. GDPR, ePrivacy & Google Consent Mode v2</h2>
                <p>
                    OmniSignal supports prior-consent cookie gating and passes explicit <code class="text-emerald-300 font-mono">ad_user_data</code> and <code class="text-emerald-300 font-mono">ad_personalization</code> signals to Google Ads to comply with the European Digital Markets Act (DMA).
                </p>
            </section>

            <section>
                <h2 class="text-lg font-bold text-white mb-2">3. 90-Day Automated Retention Pruning</h2>
                <p>
                    All offline lead tracking records stored in self-hosted databases implement Laravel's native <code class="text-emerald-300 font-mono">Prunable</code> trait and are automatically erased after 90 days (configurable).
                </p>
            </section>

            <section>
                <h2 class="text-lg font-bold text-white mb-2">4. Your Rights (Access & Erasure)</h2>
                <p>
                    Under GDPR and CCPA, users have the Right to Erasure. Applications using OmniSignal can call <code class="text-emerald-300 font-mono">OmniSignal::forgetVisitor()</code> to instantly expunge any visitor tracking identifier upon request.
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
