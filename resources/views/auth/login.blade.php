<x-layouts.app>
    <div class="min-h-[80vh] flex items-center justify-center py-12 px-4 bg-white">
        <div class="w-full max-w-md">
            
            <div class="mb-10">
                <h1 class="text-3xl font-bold text-black tracking-tight">Sign in</h1>
                <p class="text-gray-600 mt-2">Welcome back to Sport Manager.</p>
            </div>

            <div class="border border-gray-300 p-8">
                @if($errors->any())
                    <div class="mb-6 border-l-4 border-red-600 bg-red-50 p-4">
                        @foreach($errors->all() as $error)<p class="text-sm font-bold text-red-900">{{ $error }}</p>@endforeach
                    </div>
                @endif

                <form method="POST" action="/login" class="space-y-6">
                    @csrf
                    <div>
                        <label for="email" class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Email Address</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                               class="w-full border border-gray-300 p-3 text-black focus:outline-none focus:border-black focus:ring-1 focus:ring-black rounded-none">
                    </div>
                    <div>
                        <label for="password" class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Password</label>
                        <input type="password" name="password" id="password" required
                               class="w-full border border-gray-300 p-3 text-black focus:outline-none focus:border-black focus:ring-1 focus:ring-black rounded-none">
                    </div>
                    <div class="flex items-center">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="remember" id="remember" class="w-4 h-4 border-gray-300 rounded-none text-black focus:ring-black">
                            <span class="text-sm font-medium text-gray-700">Remember me</span>
                        </label>
                    </div>
                    <div class="pt-4 border-t border-gray-200">
                        <button type="submit" class="w-full bg-black text-white py-3 font-bold text-sm hover:bg-gray-800 transition-colors">
                            Sign In
                        </button>
                    </div>
                </form>
            </div>

            <div class="mt-8 text-center border-t border-gray-200 pt-8">
                <p class="text-sm text-gray-600">Don't have an account?
                    <a href="/register" class="font-bold text-black hover:underline ml-1">Create one</a>
                </p>
            </div>
        </div>
    </div>
</x-layouts.app>
