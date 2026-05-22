<x-layouts.app>
    <div class="min-h-[60vh] flex items-center justify-center py-12 px-4">
        <div class="max-w-md w-full bg-white rounded-lg shadow-md p-8">
            <h2 class="text-2xl font-bold text-gray-900 text-center mb-6">Sign In</h2>

            @if($errors->any())
                <div class="bg-red-50 text-red-600 p-3 rounded-md mb-4 text-sm">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="/login" class="space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4 py-2">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" id="password" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-4 py-2">
                </div>
                <div class="flex items-center">
                    <input type="checkbox" name="remember" id="remember" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <label for="remember" class="ml-2 text-sm text-gray-600">Remember me</label>
                </div>
                <button type="submit" class="w-full bg-gray-900 text-white rounded-md px-6 py-3 font-semibold hover:bg-gray-800 transition">
                    Sign In
                </button>
            </form>
            <p class="text-center text-sm text-gray-600 mt-4">
                Don't have an account? <a href="/register" class="text-indigo-600 hover:text-indigo-500 font-medium">Register</a>
            </p>
        </div>
    </div>
</x-layouts.app>
