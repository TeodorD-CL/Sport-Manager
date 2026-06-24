<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sport Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        'stone-bg': '#f4f3ed', // Warm off-white from the design
                    }
                }
            }
        }
    </script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    @stack('head')
    @livewireStyles
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', sans-serif; background-color: #f4f3ed; color: #111; }
        
        /* Remove default select styling to match the raw design */
        select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: none;
        }
        
        input:focus, select:focus, textarea:focus, button:focus {
            outline: none;
        }

        /* Brutalist Flatpickr Theme */
        .flatpickr-calendar {
            background: #f4f3ed !important;
            border: 1px solid #111 !important;
            border-radius: 0 !important;
            box-shadow: 4px 4px 0 rgba(0,0,0,1) !important;
            padding: 0 !important;
            width: 300px !important;
        }
        .flatpickr-calendar::before, .flatpickr-calendar::after { display: none !important; }
        .flatpickr-months {
            background: #111 !important;
            border-bottom: 1px solid #111 !important;
            border-radius: 0 !important;
            padding: 4px 0 !important;
        }
        .flatpickr-month { color: #fff !important; fill: #fff !important; }
        .flatpickr-current-month { font-family: 'Inter', sans-serif !important; font-weight: bold !important; font-size: 14px !important; }
        .flatpickr-current-month .flatpickr-monthDropdown-months { background: #111 !important; border-radius: 0 !important; }
        .flatpickr-prev-month, .flatpickr-next-month { color: #fff !important; fill: #fff !important; }
        .flatpickr-prev-month:hover, .flatpickr-next-month:hover { color: #ccc !important; fill: #ccc !important; }
        .flatpickr-weekdays {
            background: #f4f3ed !important;
            border-bottom: 1px solid #111 !important;
        }
        span.flatpickr-weekday {
            color: #111 !important;
            font-family: 'Inter', sans-serif !important;
            font-weight: bold !important;
            font-size: 10px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.1em !important;
        }
        .flatpickr-day {
            border-radius: 0 !important;
            color: #111 !important;
            font-family: 'Inter', sans-serif !important;
            font-weight: 600 !important;
            font-size: 13px !important;
            border: 1px solid transparent !important;
        }
        .flatpickr-day:hover {
            background: #e8e5dc !important;
            border-color: #111 !important;
            color: #111 !important;
        }
        .flatpickr-day.selected {
            background: #111 !important;
            border-color: #111 !important;
            color: #fff !important;
            font-weight: bold !important;
        }
        .flatpickr-day.flatpickr-disabled {
            color: #aaa !important;
        }
    </style>
</head>
<body class="font-sans antialiased min-h-screen flex flex-col">

    <!-- Navigation -->
    <header class="border-b border-black">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex justify-between items-center h-[72px]">
                <!-- Logo -->
                <a href="/" class="flex items-center gap-2 h-full py-5">
                    <img src="/images/logo_icon.jpg" alt="Sport Manager" class="h-6 w-6 object-contain mix-blend-multiply">
                    <span class="font-bold text-[15px] tracking-tight text-black mt-0.5">Sport Manager</span>
                </a>

                <!-- Desktop nav -->
                <nav class="hidden md:flex items-center gap-8" x-data="{}">
                    <a href="/" class="text-[13px] font-bold text-black border-b-2 {{ request()->is('/') ? 'border-black' : 'border-transparent hover:border-black' }} pb-1 mt-1">Browse</a>
                    
                    @auth
                        @if(Auth::user()->hasAdminPanelAccess())
                            <a href="/admin" class="text-[13px] font-bold text-gray-500 hover:text-black transition-colors">Admin</a>
                        @endif
                        <a href="/dashboard" class="text-[13px] font-bold {{ request()->is('dashboard') ? 'text-black border-b-2 border-black pb-1 mt-1' : 'text-gray-500 hover:text-black transition-colors' }}">Bookings</a>
                        <a href="/profile" class="text-[13px] font-bold {{ request()->is('profile') ? 'text-black border-b-2 border-black pb-1 mt-1' : 'text-gray-500 hover:text-black transition-colors' }}">Profile</a>
                        <form method="POST" action="/logout" class="inline">
                            @csrf
                            <button type="submit" class="text-[13px] font-bold text-gray-500 hover:text-black transition-colors">Sign out</button>
                        </form>
                    @else
                        <a href="/login" class="text-[13px] font-bold text-gray-500 hover:text-black transition-colors">Sign in</a>
                        <a href="/register" class="text-[13px] font-bold text-gray-500 hover:text-black transition-colors">Register</a>
                    @endauth
                </nav>

                <!-- Mobile hamburger -->
                <button @click="open = !open" class="md:hidden p-2 text-black" x-data="{ open: false }">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>
    </header>

    <main class="flex-1">
        {{ $slot }}
    </main>

    @livewireScripts
    @livewire('chatbot')
    @stack('scripts')
</body>
</html>
