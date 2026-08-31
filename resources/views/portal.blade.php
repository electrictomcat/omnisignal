<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer License & Account Portal &bull; OmniSignal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#090D16] text-slate-200 antialiased selection:bg-emerald-500/20 selection:text-emerald-300">

    <div class="fixed inset-0 radial-zen-mid pointer-events-none -z-10"></div>

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
                Enter the email address you bought with and we'll send you a secure link to your license keys, domain activations and invoices.
            </p>
        </div>

        @if(session('success'))
            <div class="mb-8 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm flex items-center gap-2">
                <span>✓</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('status'))
            <div class="mb-8 p-4 rounded-xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-200 text-sm flex items-start gap-2">
                <span>✉</span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="mb-8 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-start gap-2">
                <span>!</span>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        @unless($unlocked)
            <!-- Request an access link -->
            <div class="rounded-2xl bg-slate-900/80 border border-slate-800 p-6 sm:p-8 shadow-2xl mb-12">
                <form action="{{ route('portal.lookup') }}" method="POST" class="flex flex-col sm:flex-row gap-4">
                    @csrf
                    <div class="flex-1">
                        <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 font-mono mb-2">
                            Your purchase email
                        </label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="buyer@example.com" required autocomplete="email"
                            class="w-full px-4 py-3 bg-[#080d16] border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-emerald-400 font-mono text-sm">
                        <p class="mt-2 text-xs text-slate-500">
                            We'll email a secure link that opens your licenses. Keys are never shown to anyone who hasn't received that email.
                        </p>
                    </div>
                    <div class="sm:self-start sm:pt-7">
                        <button type="submit" class="w-full sm:w-auto px-6 py-3 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-sm rounded-xl transition shadow-lg shadow-emerald-500/20 active:scale-95">
                            Email me a link
                        </button>
                    </div>
                </form>
            </div>
        @endunless

        <!-- License Results -->
        @if($unlocked)
            <div class="mb-8 text-center text-xs text-slate-500 font-mono">
                Signed in as {{ $email }}
            </div>
            @if($licenses->isEmpty())
                <div class="rounded-2xl bg-slate-900/40 border border-slate-800 p-12 text-center">
                    <div class="text-4xl mb-3">🔍</div>
                    <h3 class="text-lg font-bold text-white">No License Found</h3>
                    <p class="text-slate-400 text-sm mt-1 max-w-md mx-auto">
                        There are no licenses on this address any more. If you bought under a different email, request a link for that one instead.
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

                            {{-- Hosted ad-platform connections --}}
                            <div class="mt-8">
                                <h3 class="text-sm font-bold text-slate-300 uppercase tracking-wider font-mono mb-3">
                                    Ad platform connections
                                </h3>

                                @php($google = $license->connections->firstWhere('channel', 'google'))

                                <div class="rounded-xl bg-[#080d16] border border-slate-800 px-4 py-3 flex items-center justify-between gap-4 text-sm">
                                    <div>
                                        <div class="text-slate-300 font-semibold">Google Ads</div>
                                        @if($google && $google->isUsable())
                                            <div class="text-xs text-slate-500 mt-0.5">
                                                {{ $google->account_name ?: $google->account_id }}
                                                <span class="font-mono">({{ $google->account_id }})</span>
                                            </div>
                                        @elseif($google && $google->status === 'needs_reauth')
                                            <div class="text-xs text-amber-400 mt-0.5">
                                                Needs reconnecting &mdash; {{ $google->last_error }}
                                            </div>
                                        @elseif($google)
                                            <div class="text-xs text-amber-400 mt-0.5">Authorised, but no account chosen yet.</div>
                                        @else
                                            <div class="text-xs text-slate-500 mt-0.5">
                                                Not connected. Google Ads uploads run through us because they need
                                                credentials that cannot ship in a plugin.
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-3 shrink-0">
                                        @if($google && $google->isUsable())
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                ● Connected
                                            </span>
                                            <form method="POST" action="{{ route('portal.connect.destroy', $google) }}"
                                                  onsubmit="return confirm('Disconnect Google Ads? Stored credentials are deleted immediately.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs text-rose-400 hover:text-rose-300 hover:underline">
                                                    Disconnect
                                                </button>
                                            </form>
                                        @elseif($google)
                                            <a href="{{ route('portal.connect.google.setup', $google) }}"
                                               class="px-3.5 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-bold transition">
                                                Finish setup
                                            </a>
                                        @else
                                            <a href="{{ route('portal.connect.google', $license) }}"
                                               class="px-3.5 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs font-bold transition">
                                                Connect
                                            </a>
                                        @endif
                                    </div>
                                </div>
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
