<div class="bg-white min-h-screen pb-16">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 pt-12 pb-8 border-b border-gray-200">
        <h1 class="text-3xl font-bold text-black tracking-tight">Profile Settings</h1>
        <p class="text-gray-600 mt-2">Manage your account details and password.</p>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-8">
        @if(session('success'))
            <div class="mb-8 border-l-4 border-black bg-gray-50 p-4">
                <p class="text-sm font-bold text-black">{{ session('success') }}</p>
            </div>
        @endif

        <div class="border border-gray-200 p-6 sm:p-8 bg-white">
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
                    <label for="phone" class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Phone</label>
                    <input type="text" id="phone" wire:model="phone"
                           class="w-full border border-gray-300 p-3 text-black focus:outline-none focus:border-black focus:ring-1 focus:ring-black rounded-none">
                    @error('phone') <span class="text-xs font-bold text-red-600 mt-2 block">{{ $message }}</span> @enderror
                </div>

                <hr class="border-gray-200 my-8">

                <div>
                    <h3 class="text-lg font-bold text-black mb-6">Change Password</h3>
                    <div class="space-y-5">
                        <div>
                            <label for="current_password" class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Current Password</label>
                            <input type="password" id="current_password" wire:model="current_password"
                                   class="w-full border border-gray-300 p-3 text-black focus:outline-none focus:border-black focus:ring-1 focus:ring-black rounded-none">
                            @error('current_password') <span class="text-xs font-bold text-red-600 mt-2 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="password" class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">New Password</label>
                            <input type="password" id="password" wire:model="password"
                                   class="w-full border border-gray-300 p-3 text-black focus:outline-none focus:border-black focus:ring-1 focus:ring-black rounded-none">
                            @error('password') <span class="text-xs font-bold text-red-600 mt-2 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Confirm New Password</label>
                            <input type="password" id="password_confirmation" wire:model="password_confirmation"
                                   class="w-full border border-gray-300 p-3 text-black focus:outline-none focus:border-black focus:ring-1 focus:ring-black rounded-none">
                        </div>
                    </div>
                </div>

                <div class="pt-6 flex items-center gap-4">
                    <button type="submit" wire:loading.attr="disabled"
                            class="bg-black text-white px-8 py-3 font-bold text-sm hover:bg-gray-800 transition-colors disabled:opacity-50">
                        <span wire:loading.remove wire:target="updateProfile">Save Changes</span>
                        <span wire:loading wire:target="updateProfile">Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
