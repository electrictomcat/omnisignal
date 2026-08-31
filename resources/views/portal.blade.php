<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer License & Account Portal &bull; OmniSignal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        code, pre, .font-mono { font-family: 'JetBrains Mono', monospace; }
        .radial-zen {
            background: radial-gradient(circle at 50% 10%, rgba(16, 185, 129, 0.1) 0%, rgba(6, 182, 212, 0.04) 30%, transparent 65%);
        }
    </style>
</head>
<body class="bg-[#090D16] text-slate-200 antialiased selection:bg-emerald-500/20 selection:text-emerald-300">

    <div class="fixed inset-0 radial-zen pointer-events-none -z-10"></div>

    <!-- Header -->
    <header class="sticky top-0 z-50 backdrop-blur-md bg-[#090D16]/90 border-b border-slate-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="/" class="flex items-center space-x-3 group">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-500/20 via-cyan-500/20 to-indigo-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400 font-bold text-lg group-hover:border-emerald-400/60 transition shadow-lg">
                        ॐ
                    </div>
                    <span class="text-lg font-bold tracking-tight text-white flex items-center gap-1.5">
                        OmniSignal
                        <span class="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Portal</span>
                    </span>
                </a>
            </div>

            <div class="flex items-center space-x-5 text-sm font-medium">
                <a href="/" class="text-slate-400 hover:text-white transition">Home</a>
                <a href="/docs" class="text-slate-400 hover:text-white transition">Documentation</a>
                <a href="/refunds" class="text-slate-400 hover:text-white transition">Refunds</a>
                <a href="https://app.lemonsqueezy.com/my-orders" target="_blank" class="rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white px-3.5 py-1.5 text-xs font-semibold transition flex items-center gap-1.5">
                    <span>Lemon Squeezy Billing</span>
                    <span class="text-slate-400 text-[10px]">↗</span>
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        
        <!-- Intro -->
        <div class="text-center mb-12">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 text-xs font-medium text-emerald-400 mb-3 border border-emerald-500/20">
                <span>🔑 Self-Service Account & License Hub</span>
            </div>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-white">Manage Your OmniSignal License</h1>
            <p class="mt-3 text-slate-400 text-base max-w-xl mx-auto">
                Look up your license keys, view active domain activations, manage your subscription, and download invoices.
            </p>
        </div>

        @if(session('success'))
            <div class="mb-8 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm flex items-center gap-2">
                <span>✓</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <!-- Search Form -->
        <div class="rounded-2xl bg-slate-900/80 border border-slate-800 p-6 sm:p-8 shadow-2xl mb-12">
            <form action="{{ route('portal.lookup') }}" method="POST" class="flex flex-col sm:flex-row gap-4">
                @csrf
                <div class="flex-1">
                    <label for="query" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 font-mono mb-2">
                        Enter Buyer Email or License Key
                    </label>
                    <input type="text" id="query" name="query" value="{{ $query }}" placeholder="e.g. buyer@example.com or OMNI-XXXX-XXXX" required
                        class="w-full px-4 py-3 bg-[#080d16] border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-emerald-400 font-mono text-sm">
                </div>
                <div class="sm:self-end">
                    <button type="submit" class="w-full sm:w-auto px-6 py-3 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-sm rounded-xl transition shadow-lg shadow-emerald-500/20 active:scale-95">
                        Lookup License
                    </button>
                </div>
            </form>
        </div>

        <!-- License Results -->
        @if(!empty($query))
            @if($licenses->isEmpty())
                <div class="rounded-2xl bg-slate-900/40 border border-slate-800 p-12 text-center">
                    <div class="text-4xl mb-3">🔍</div>
                    <h3 class="text-lg font-bold text-white">No License Found</h3>
                    <p class="text-slate-400 text-sm mt-1 max-w-md mx-auto">
                        We couldn't find any licenses matching <code class="text-slate-300 font-mono">{{ $query }}</code>. Please make sure you entered the email address used during Lemon Squeezy checkout.
                    </p>
                    <div class="mt-6">
                        <a href="https://app.lemonsqueezy.com/my-orders" target="_blank" class="text-xs text-emerald-400 hover:underline">
                            Search on Lemon Squeezy My Orders Portal →
                        </a>
                    </div>
                </div>
            @else
                <div class="space-y-8">
                    @foreach($licenses as $license)
                        <div class="rounded-3xl bg-slate-900 border {{ $license->isActive() ? 'border-emerald-500/40' : 'border-rose-500/40' }} p-6 sm:p-8 shadow-2xl relative overflow-hidden">
                            <!-- Status Pill -->
                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
                                <div>
                                    <div class="flex items-center gap-3">
                                        <h2 class="text-xl font-bold text-white capitalize">OmniSignal {{ $license->tier }}</h2>
                                        @if($license->status === 'active')
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                ● Active
                                            </span>
                                        @elseif($license->status === 'refunded')
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                                ● Refunded
                                            </span>
                                        @else
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                                ● {{ ucfirst($license->status) }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-slate-400 mt-1">
                                        Order #{{ $license->order_id }} &bull; Registered to <strong class="text-slate-300">{{ $license->customer_email }}</strong>
                                    </p>
                                </div>

                                <div class="text-left sm:text-right text-xs text-slate-400">
                                    <span>Renewal / Expiration:</span>
                                    <p class="text-white font-mono mt-0.5">{{ $license->expires_at ? $license->expires_at->format('M d, Y') : 'Lifetime' }}</p>
                                </div>
                            </div>

                            <!-- License Key Box -->
                            <div class="mt-6 p-4 rounded-xl bg-[#080d16] border border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                                <div>
                                    <span class="text-[10px] uppercase font-mono tracking-wider text-slate-500 block">License Key</span>
                                    <code id="key-{{ $license->id }}" class="text-emerald-300 font-mono text-base font-semibold">{{ $license->license_key }}</code>
                                </div>
                                <button type="button" onclick="navigator.clipboard.writeText('{{ $license->license_key }}'); this.textContent = 'Copied! ✓'; setTimeout(() => this.textContent = 'Copy Key', 2000);"
                                    class="px-3.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-white transition">
                                    Copy Key
                                </button>
                            </div>

                            <!-- Activated Domains Section -->
                            <div class="mt-8">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-sm font-bold text-slate-300 uppercase tracking-wider font-mono">
                                        Domain Activations ({{ $license->activation_count }}/{{ $license->activation_limit > 1000 ? '∞' : $license->activation_limit }})
                                    </h3>
                                </div>

                                @if(empty($license->instances))
                                    <div class="p-4 rounded-xl bg-[#080d16]/50 border border-slate-800 text-xs text-slate-500 italic">
                                        No domains have been activated with this key yet. Enter this key in your Laravel or WooCommerce settings to activate.
                                    </div>
                                @else
                                    <div class="divide-y divide-slate-800 rounded-xl bg-[#080d16] border border-slate-800 overflow-hidden">
                                        @foreach($license->instances as $instance)
                                            <div class="px-4 py-3 flex items-center justify-between text-sm">
                                                <div class="flex items-center gap-2 font-mono text-slate-300 text-xs">
                                                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                                    <span>{{ $instance }}</span>
                                                </div>
                                                <form action="{{ route('portal.deactivate') }}" method="POST" onsubmit="return confirm('Are you sure you want to deactivate {{ $instance }}?');">
                                                    @csrf
                                                    <input type="hidden" name="license_id" value="{{ $license->id }}">
                                                    <input type="hidden" name="domain" value="{{ $instance }}">
                                                    <button type="submit" class="text-xs text-rose-400 hover:text-rose-300 hover:underline">
                                                        Deactivate
                                                    </button>
                                                </form>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <!-- Lemon Squeezy Customer Management Action Links -->
                            <div class="mt-8 pt-6 border-t border-slate-800 flex flex-wrap items-center justify-between gap-4 text-xs">
                                <div class="flex items-center gap-4">
                                    <a href="https://app.lemonsqueezy.com/my-orders" target="_blank" class="text-emerald-400 hover:underline font-semibold flex items-center gap-1">
                                        <span>Update Credit Card / Invoices</span>
                                        <span>↗</span>
                                    </a>
                                    <a href="https://app.lemonsqueezy.com/my-orders" target="_blank" class="text-slate-400 hover:text-rose-400 hover:underline">
                                        Cancel Subscription
                                    </a>
                                </div>
                                <div>
                                    <a href="/refunds" class="text-slate-400 hover:text-white hover:underline">
                                        Refund Policy & Guarantee →
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

        <!-- Self Service FAQs -->
        <div class="mt-20 pt-12 border-t border-slate-800/80">
            <h2 class="text-xl font-bold text-white mb-6 text-center">Frequently Asked Account Questions</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                <div class="p-6 rounded-2xl bg-slate-900/60 border border-slate-800">
                    <h3 class="font-bold text-white">How do I cancel my subscription?</h3>
                    <p class="text-slate-400 text-xs mt-2 leading-relaxed">
                        Log into the <a href="https://app.lemonsqueezy.com/my-orders" target="_blank" class="text-emerald-400 hover:underline">Lemon Squeezy Customer Portal</a> with your purchase email. You can cancel with one click anytime. Your license remains active until the end of your billing period.
                    </p>
                </div>
                <div class="p-6 rounded-2xl bg-slate-900/60 border border-slate-800">
                    <h3 class="font-bold text-white">Where are my tax invoices & receipts?</h3>
                    <p class="text-slate-400 text-xs mt-2 leading-relaxed">
                        Official EU/UK VAT and US sales tax invoices with reverse-charge support are sent to your email after each renewal and accessible anytime in the Lemon Squeezy portal.
                    </p>
                </div>
                <div class="p-6 rounded-2xl bg-slate-900/60 border border-slate-800">
                    <h3 class="font-bold text-white">What is your refund policy?</h3>
                    <p class="text-slate-400 text-xs mt-2 leading-relaxed">
                        We offer a 100% money-back guarantee within 14 days of purchase. Visit our <a href="/refunds" class="text-emerald-400 hover:underline">Refund Policy page</a> to request a prompt refund.
                    </p>
                </div>
                <div class="p-6 rounded-2xl bg-slate-900/60 border border-slate-800">
                    <h3 class="font-bold text-white">How do I move a license to a new domain?</h3>
                    <p class="text-slate-400 text-xs mt-2 leading-relaxed">
                        Look up your key above, click <strong>Deactivate</strong> next to the old domain, and enter the key on your new site.
                    </p>
                </div>
            </div>
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
