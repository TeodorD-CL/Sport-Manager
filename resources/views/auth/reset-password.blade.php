<x-layouts.app title="Choose a new password – Sport Manager">
    <div class="min-h-[80vh] flex items-center justify-center py-12 px-4">
        <div class="w-full max-w-md">

            <div class="mb-10">
                <h1 class="text-3xl font-bold text-black tracking-tight">Choose a new password</h1>
                <p class="text-gray-600 mt-2">Use at least 8 characters.</p>
            </div>

            <div class="border border-gray-300 bg-white p-8">
                @if($errors->any())
                    <div class="mb-6 border-l-4 border-red-600 bg-red-50 p-4">
                        @foreach($errors->all() as $error)<p class="text-sm font-bold text-red-900">{{ $error }}</p>@endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('password.update') }}" class="space-y-6">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <div>
                        <label for="email" class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Email Address</label>
                        <input type="email" name="email" id="email" autocomplete="email" value="{{ old('email', $email) }}" required
                               class="w-full border border-gray-300 p-3 text-black focus:outline-none focus:border-black focus:ring-1 focus:ring-black rounded-none">
                    </div>
                    <div>
                        <label for="password" class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">New Password</label>
                        <input type="password" name="password" id="password" autocomplete="new-password" required autofocus
                               class="w-full border border-gray-300 p-3 text-black focus:outline-none focus:border-black focus:ring-1 focus:ring-black rounded-none">
                    </div>
                    <div>
                        <label for="password_confirmation" class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Confirm New Password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password" required
                               class="w-full border border-gray-300 p-3 text-black focus:outline-none focus:border-black focus:ring-1 focus:ring-black rounded-none">
                    </div>
                    <div class="pt-4 border-t border-gray-200">
                        <button type="submit" class="w-full bg-black text-white py-3 font-bold text-sm hover:bg-gray-800 transition-colors">
                            Reset password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
