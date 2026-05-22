<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Sport-Manager</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    @stack('head')
    @livewireStyles
</head>
<body class="font-sans antialiased bg-gray-50 min-h-screen flex flex-col">
    <nav x-data="{ open: false }" class="bg-gray-900 text-white relative z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <a href="/" class="text-xl font-bold tracking-wide">Sport-Manager</a>

                {{-- Desktop nav --}}
                <div class="hidden md:flex items-center space-x-4">
                    @auth
                        @if (Auth::user()->hasAdminPanelAccess())
                            <a href="/admin" class="text-sm bg-amber-600 hover:bg-amber-500 text-white px-3 py-1.5 rounded-full transition">Admin Panel</a>
                        @endif
                        <a href="/dashboard" class="text-sm {{ request()->is('dashboard') ? 'text-white font-semibold' : 'text-gray-300 hover:text-white' }} transition">My Bookings</a>
                        <form method="POST" action="/logout" class="inline">
                            @csrf
                            <button type="submit" class="text-sm text-gray-300 hover:text-white transition">Logout</button>
                        </form>
                        <a href="/profile" class="flex items-center gap-2 px-3 py-1.5 rounded-full {{ request()->is('profile') ? 'bg-gray-700' : 'hover:bg-gray-800' }} transition">
                            <div class="w-7 h-7 rounded-full bg-indigo-500 flex items-center justify-center text-xs font-bold">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </div>
                            <span class="text-sm">{{ Auth::user()->name }}</span>
                        </a>
                    @else
                        <a href="/login" class="text-sm {{ request()->is('login') ? 'text-white font-semibold' : 'text-gray-300 hover:text-white' }} transition">Login</a>
                        <a href="/register" class="text-sm bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-1.5 rounded-full transition">Register</a>
                    @endauth
                </div>

                {{-- Mobile hamburger --}}
                <button @click="open = !open" class="md:hidden p-2 rounded-md hover:bg-gray-800 transition" aria-label="Toggle menu">
                    <svg x-show="!open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg x-show="open" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Mobile menu --}}
        <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" class="md:hidden border-t border-gray-700 bg-gray-900">
            <div class="px-4 py-3 space-y-2">
                <a href="/" class="block text-sm {{ request()->is('/') ? 'text-white font-semibold' : 'text-gray-300 hover:text-white' }} py-2 transition">Home</a>
                @auth
                    @if (Auth::user()->hasAdminPanelAccess())
                        <a href="/admin" class="block text-sm text-amber-300 hover:text-amber-200 py-2 transition">Admin Panel</a>
                    @endif
                    <a href="/dashboard" class="block text-sm {{ request()->is('dashboard') ? 'text-white font-semibold' : 'text-gray-300 hover:text-white' }} py-2 transition">My Bookings</a>
                    <a href="/profile" class="block text-sm {{ request()->is('profile') ? 'text-white font-semibold' : 'text-gray-300 hover:text-white' }} py-2 transition">
                        Profile ({{ Auth::user()->name }})
                    </a>
                    <form method="POST" action="/logout">
                        @csrf
                        <button type="submit" class="block w-full text-left text-sm text-gray-300 hover:text-white py-2 transition">Logout</button>
                    </form>
                @else
                    <a href="/login" class="block text-sm {{ request()->is('login') ? 'text-white font-semibold' : 'text-gray-300 hover:text-white' }} py-2 transition">Login</a>
                    <a href="/register" class="block text-sm {{ request()->is('register') ? 'text-white font-semibold' : 'text-gray-300 hover:text-white' }} py-2 transition">Register</a>
                @endauth
            </div>
        </div>
    </nav>

    <main class="flex-1">
        {{ $slot }}
    </main>

    <footer class="bg-gray-900 text-gray-400 py-8">
        <div class="max-w-7xl mx-auto px-4 text-center text-sm">
            &copy; {{ date('Y') }} Sport-Manager. All rights reserved.
        </div>
    </footer>

    @livewireScripts

    <style>[x-cloak] { display: none !important; }</style>

    <script>
        // Auto-dismiss flash messages after 4 seconds
        function attachAutoHide(root) {
            root.querySelectorAll('.flash-message:not([data-auto-hide])').forEach(el => {
                el.setAttribute('data-auto-hide', '1');
                setTimeout(() => {
                    el.style.transition = 'opacity 0.5s ease';
                    el.style.opacity = '0';
                    setTimeout(() => el.remove(), 500);
                }, 4000);
            });
        }
        document.addEventListener('DOMContentLoaded', () => attachAutoHide(document));
        document.addEventListener('livewire:navigated', () => attachAutoHide(document));
        const _flashObserver = new MutationObserver(mutations => {
            mutations.forEach(m => m.addedNodes.forEach(n => {
                if (n.nodeType === 1) attachAutoHide(n.parentElement || n);
            }));
        });
        document.addEventListener('DOMContentLoaded', () => {
            _flashObserver.observe(document.body, { childList: true, subtree: true });
        });
    </script>

    @stack('scripts')
</body>
</html>
