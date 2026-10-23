<div class="bg-white min-h-screen pb-16">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 pt-12 pb-8 border-b border-gray-200">
        <h1 class="text-3xl font-bold text-black tracking-tight">Profile Settings</h1>
        <p class="text-gray-600 mt-2">Manage your account details and password.</p>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-8 space-y-8">
        <dl class="grid grid-cols-2 sm:grid-cols-4 border border-gray-200 divide-x divide-gray-200">
            <div class="p-4">
                <dt class="text-xs font-bold text-gray-500 uppercase tracking-wide">Bookings</dt>
                <dd class="text-2xl font-bold text-black mt-1">{{ $totalBookings }}</dd>
            </div>
            <div class="p-4">
                <dt class="text-xs font-bold text-gray-500 uppercase tracking-wide">Upcoming</dt>
                <dd class="text-2xl font-bold text-black mt-1">{{ $upcomingBookings }}</dd>
            </div>
            <div class="p-4">
                <dt class="text-xs font-bold text-gray-500 uppercase tracking-wide">Reviews</dt>
                <dd class="text-2xl font-bold text-black mt-1">{{ $reviewsCount }}</dd>
            </div>
            <div class="p-4">
                <dt class="text-xs font-bold text-gray-500 uppercase tracking-wide">Favourite sport</dt>
                <dd class="text-2xl font-bold text-black mt-1">{{ $favouriteSport ?? '—' }}</dd>
            </div>
        </dl>

        <div class="border border-gray-200 p-6 sm:p-8 bg-white">
            <h2 class="text-lg font-bold text-black mb-6">Account Details</h2>

            @if(session('profile_success'))
                <div class="mb-6 border-l-4 border-black bg-gray-50 p-4">
                    <p class="text-sm font-bold text-black">{{ session('profile_success') }}</p>
                </div>
            @endif

            <form wire:submit="updateProfile" class="space-y-6">
                <div>
                    <label for="name" class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Full Name</label>
                    <input type="text" id="name" wire:model="name"
                           class="w-full border border-gray-300 p-3 text-black focus:outline-none focus:border-black focus:ring-1 focus:ring-black rounded-none">
                    @error('name') <span class="text-xs font-bold text-red-600 mt-2 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="email" class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Email Address</label>
                    <input type="email" id="email" wire:model="email"
                           class="w-full border border-gray-300 p-3 text-black focus:outline-none focus:border-black focus:ring-1 focus:ring-black rounded-none">
                    @error('email') <span class="text-xs font-bold text-red-600 mt-2 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="email_password" class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">
                        Current Password <span class="text-gray-400 font-normal normal-case tracking-normal ml-1">(only needed to change your email)</span>
                    </label>
                    <input type="password" id="email_password" wire:model="email_password" autocomplete="current-password"
                           class="w-full border border-gray-300 p-3 text-black focus:outline-none focus:border-black focus:ring-1 focus:ring-black rounded-none">
                    @error('email_password') <span class="text-xs font-bold text-red-600 mt-2 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="phone" class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Phone</label>
                    <input type="text" id="phone" wire:model="phone"
                           class="w-full border border-gray-300 p-3 text-black focus:outline-none focus:border-black focus:ring-1 focus:ring-black rounded-none">
                    @error('phone') <span class="text-xs font-bold text-red-600 mt-2 block">{{ $message }}</span> @enderror
                </div>

                <div class="pt-2">
                    <button type="submit" wire:loading.attr="disabled"
                            class="bg-black text-white px-8 py-3 font-bold text-sm hover:bg-gray-800 transition-colors disabled:opacity-50">
                        <span wire:loading.remove wire:target="updateProfile">Save Changes</span>
                        <span wire:loading wire:target="updateProfile">Saving...</span>
                    </button>
                </div>
            </form>
        </div>

        <div class="border border-gray-200 p-6 sm:p-8 bg-white">
            <h2 class="text-lg font-bold text-black mb-6">Change Password</h2>

            @if(session('password_success'))
                <div class="mb-6 border-l-4 border-black bg-gray-50 p-4">
                    <p class="text-sm font-bold text-black">{{ session('password_success') }}</p>
                </div>
            @endif

            <form wire:submit="updatePassword" class="space-y-5">
                <div>
                    <label for="current_password" class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Current Password</label>
                    <input type="password" id="current_password" wire:model="current_password" autocomplete="current-password"
                           class="w-full border border-gray-300 p-3 text-black focus:outline-none focus:border-black focus:ring-1 focus:ring-black rounded-none">
                    @error('current_password') <span class="text-xs font-bold text-red-600 mt-2 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="new_password" class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">New Password</label>
                    <input type="password" id="new_password" wire:model="new_password" autocomplete="new-password"
                           class="w-full border border-gray-300 p-3 text-black focus:outline-none focus:border-black focus:ring-1 focus:ring-black rounded-none">
                    @error('new_password') <span class="text-xs font-bold text-red-600 mt-2 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="new_password_confirmation" class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Confirm New Password</label>
                    <input type="password" id="new_password_confirmation" wire:model="new_password_confirmation" autocomplete="new-password"
                           class="w-full border border-gray-300 p-3 text-black focus:outline-none focus:border-black focus:ring-1 focus:ring-black rounded-none">
                </div>

                <div class="pt-2">
                    <button type="submit" wire:loading.attr="disabled"
                            class="bg-black text-white px-8 py-3 font-bold text-sm hover:bg-gray-800 transition-colors disabled:opacity-50">
                        <span wire:loading.remove wire:target="updatePassword">Update Password</span>
                        <span wire:loading wire:target="updatePassword">Updating...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
