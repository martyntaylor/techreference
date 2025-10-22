<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $pageTitle ?? 'TechReference' }} - Technical Reference for Developers</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Additional Head Content -->
    {{ $head ?? '' }}
</head>
<body class="font-sans antialiased bg-gray-50 dark:bg-gray-900">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700" x-data="{ mobileMenuOpen: false }">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <!-- Logo -->
                        <div class="shrink-0 flex items-center">
                            <a href="{{ route('home') }}" class="text-xl font-bold text-gray-900 dark:text-white">
                                TechReference
                            </a>
                        </div>

                        <!-- Desktop Navigation Links -->
                        <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
                            <a href="{{ route('home') }}" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-700">
                                Home
                            </a>
                            <!-- Add more navigation items as needed -->
                        </div>
                    </div>

                    <div class="flex items-center">
                        <!-- Desktop Search -->
                        <div class="hidden sm:flex sm:items-center sm:ml-6">
                            <form action="{{ route('search') }}" method="GET" class="relative">
                                <input
                                    type="text"
                                    name="q"
                                    placeholder="Search ports, errors..."
                                    class="w-64 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                                    value="{{ request('q') }}"
                                >
                            </form>
                        </div>

                        <!-- Mobile Menu Button -->
                        <button
                            @click="mobileMenuOpen = !mobileMenuOpen"
                            type="button"
                            class="sm:hidden inline-flex items-center justify-center p-2 rounded-md text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500 transition"
                            :aria-expanded="mobileMenuOpen.toString()"
                            aria-controls="mobile-menu"
                            aria-label="Toggle navigation menu"
                        >
                            <!-- Hamburger Icon (closed state) -->
                            <svg
                                x-show="!mobileMenuOpen"
                                class="h-6 w-6"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            <!-- Close Icon (open state) -->
                            <svg
                                x-show="mobileMenuOpen"
                                class="h-6 w-6"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                                x-cloak
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Menu -->
            <div
                x-show="mobileMenuOpen"
                @click.away="mobileMenuOpen = false"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 transform -translate-y-2"
                x-transition:enter-end="opacity-100 transform translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 transform translate-y-0"
                x-transition:leave-end="opacity-0 transform -translate-y-2"
                class="sm:hidden"
                id="mobile-menu"
                x-cloak
            >
                <div class="pt-2 pb-3 space-y-1 border-t border-gray-200 dark:border-gray-700">
                    <!-- Mobile Navigation Links -->
                    <a href="{{ route('home') }}" class="block pl-3 pr-4 py-2 border-l-4 border-transparent text-base font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-600 transition">
                        Home
                    </a>
                    <!-- Add more navigation items as needed -->
                </div>

                <!-- Mobile Search -->
                <div class="pt-4 pb-3 border-t border-gray-200 dark:border-gray-700">
                    <div class="px-4">
                        <form action="{{ route('search') }}" method="GET" class="relative">
                            <input
                                type="text"
                                name="q"
                                placeholder="Search ports, errors..."
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                                value="{{ request('q') }}"
                            >
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main>
            {{ $slot }}
        </main>

        <!-- Footer -->
        <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-12">
            <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
                <div class="text-center text-sm text-gray-500 dark:text-gray-400">
                    &copy; {{ date('Y') }} TechReference. Technical reference for developers.
                </div>
            </div>
        </footer>
    </div>

    <!-- Additional Scripts -->
    {{ $scripts ?? '' }}
</body>
</html>
