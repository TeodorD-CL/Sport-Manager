<div class="max-w-3xl mx-auto px-4 py-8">

    {{-- Header: avatar + name + member since --}}
    <div class="flex items-center gap-5 mb-8">
        <div class="w-16 h-16 rounded-full bg-indigo-600 flex items-center justify-center text-white text-2xl font-bold select-none flex-shrink-0">
            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}{{ strtoupper(substr(strstr(auth()->user()->name, ' '), 1, 1) ?: '') }}
        </div>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ auth()->user()->name }}</h1>
            <p class="text-sm text-gray-500">Member since {{ auth()->user()->created_at->format('F Y') }}</p>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow-sm p-4 text-center">
            <p class="text-2xl font-bold text-indigo-600">{{ $totalBookings }}</p>
            <p class="text-xs text-gray-500 mt-1">Total Bookings</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-4 text-center">
            <p class="text-2xl font-bold text-green-600">{{ $upcomingBookings }}</p>
            <p class="text-xs text-gray-500 mt-1">Upcoming</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-4 text-center">
            <p class="text-2xl font-bold text-amber-500">{{ $reviewsCount }}</p>
            <p class="text-xs text-gray-500 mt-1">Reviews Left</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-4 text-center">
            <p class="text-2xl font-bold text-gray-800">{{ $favouriteSport ?? '–' }}</p>
            <p class="text-xs text-gray-500 mt-1">Favourite Sport</p>
        </div>
    </div>

    {{-- Personal Information --}}
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-6">Personal Information</h2>

        @if(session('profile_success'))
            <div class="flash-message mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                {{ session('profile_success') }}
            </div>
        @endif

        <form wire:submit="updateProfile" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                <input wire:model="name" type="text" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-500 @enderror">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                <input wire:model="email" type="email" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('email') border-red-500 @enderror">
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number <span class="text-gray-400 font-normal">(optional)</span></label>
                <input wire:model="phone" type="text" placeholder="+389 ..." class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('phone') border-red-500 @enderror">
                @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end">
                <button type="submit" wire:loading.attr="disabled" class="bg-indigo-600 text-white px-5 py-2 rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition flex items-center gap-2">
                    <svg wire:loading wire:target="updateProfile" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    Save Changes
                </button>
            </div>
        </form>
    </div>

    {{-- Change Password --}}
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-6">Change Password</h2>

        @if(session('password_success'))
            <div class="flash-message mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                {{ session('password_success') }}
            </div>
        @endif

        <form wire:submit="updatePassword" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                <input wire:model="current_password" type="password" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('current_password') border-red-500 @enderror">
                @error('current_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">New Password <span class="text-gray-400 font-normal">(min. 8 characters)</span></label>
                <input wire:model="new_password" type="password" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('new_password') border-red-500 @enderror">
                @error('new_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                <input wire:model="new_password_confirmation" type="password" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="flex justify-end">
                <button type="submit" wire:loading.attr="disabled" class="bg-indigo-600 text-white px-5 py-2 rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition flex items-center gap-2">
                    <svg wire:loading wire:target="updatePassword" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    Update Password
                </button>
            </div>
        </form>
    </div>
</div>
