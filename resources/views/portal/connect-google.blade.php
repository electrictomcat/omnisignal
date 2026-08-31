<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connect Google Ads &bull; OmniSignal</title>
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

    <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16">

        <div class="mb-10">
            <a href="{{ route('portal') }}" class="text-xs text-slate-400 hover:text-white transition">&larr; Back to your licences</a>
            <h1 class="mt-4 text-3xl font-extrabold text-white">Connect Google Ads</h1>
            <p class="mt-3 text-slate-400 text-sm max-w-xl">
                Choose the ad account to upload conversions into, and the conversion action they should be recorded against.
                We never see your Google password &mdash; the authorisation is held as a token you can revoke at any time.
            </p>
        </div>

        @if($error)
            <div class="mb-8 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm">
                {{ $error }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-8 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Step 1: pick the account --}}
        <div class="rounded-2xl bg-slate-900/80 border border-slate-800 p-6 sm:p-8 shadow-2xl">
            <h2 class="text-sm font-bold text-slate-300 uppercase tracking-wider font-mono mb-4">1 &middot; Ad account</h2>

            @if(empty($accounts))
                <p class="text-sm text-slate-400">
                    This Google account cannot reach any Google Ads accounts. Sign in with one that can, or ask your
                    administrator for access.
                </p>
            @else
                <div class="space-y-2">
                    @foreach($accounts as $account)
                        <a href="{{ route('portal.connect.google.setup', ['connection' => $connection->id, 'account' => $account['id']]) }}"
                           class="flex items-center justify-between px-4 py-3 rounded-xl border transition
                                  {{ $selected === $account['id']
                                     ? 'bg-emerald-500/10 border-emerald-500/40 text-white'
                                     : 'bg-[#080d16] border-slate-800 text-slate-300 hover:border-slate-600' }}">
                            <span class="text-sm">{{ $account['name'] }}</span>
                            <span class="font-mono text-xs text-slate-500">{{ $account['id'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Step 2: pick the conversion action --}}
        <div class="mt-6 rounded-2xl bg-slate-900/80 border border-slate-800 p-6 sm:p-8 shadow-2xl {{ $selected === '' ? 'opacity-50' : '' }}">
            <h2 class="text-sm font-bold text-slate-300 uppercase tracking-wider font-mono mb-4">2 &middot; Conversion action</h2>

            @if($selected === '')
                <p class="text-sm text-slate-500">Choose an ad account first.</p>
            @elseif(empty($actions))
                <p class="text-sm text-slate-400">
                    That account has no enabled conversion actions. Create one in Google Ads under
                    <span class="font-mono text-slate-300">Goals &rsaquo; Conversions</span>, then reload this page.
                </p>
            @else
                <form method="POST" action="{{ route('portal.connect.google.store', $connection) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="account_id" value="{{ $selected }}">
                    <input type="hidden" name="account_name"
                           value="{{ collect($accounts)->firstWhere('id', $selected)['name'] ?? $selected }}">

                    <select name="conversion_action" required
                            class="w-full px-4 py-3 bg-[#080d16] border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:border-emerald-400">
                        @foreach($actions as $action)
                            <option value="{{ $action['resource_name'] }}"
                                @selected($connection->credential('conversion_action') === $action['resource_name'])>
                                {{ $action['name'] }}
                            </option>
                        @endforeach
                    </select>

                    <button type="submit"
                            class="w-full sm:w-auto px-6 py-3 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-sm rounded-xl transition shadow-lg shadow-emerald-500/20 active:scale-95">
                        Finish connecting
                    </button>
                </form>
            @endif
        </div>

        <p class="mt-8 text-xs text-slate-500">
            Conversions are uploaded from omnisignal.dev on your behalf, because Google Ads requires an OAuth client
            secret and a developer token that cannot ship inside a WordPress plugin. Disconnecting deletes the stored
            authorisation immediately.
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
