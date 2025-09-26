<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'Bulk Import System') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-slate-100 text-slate-900 min-h-screen">
        <div class="min-h-screen flex flex-col">
            <header class="bg-white/90 backdrop-blur border-b border-slate-200">
                <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-between">
                    <div>
                        <h1 class="text-xl font-semibold text-slate-800">Bulk Import &amp; Media Dashboard</h1>
                        <p class="text-sm text-slate-500">Manage CSV product imports and chunked uploads from one place.</p>
                    </div>
                    <div class="flex items-center gap-3 text-sm text-slate-500">
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 border border-slate-200">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            {{ config('app.env') }} mode
                        </span>
                    </div>
                </div>
            </header>

            <main class="flex-1">
                <div class="mx-auto max-w-7xl px-6 py-10">
                    @yield('content')
                </div>
            </main>

            <footer class="bg-white border-t border-slate-200 text-sm text-slate-500">
                <div class="mx-auto max-w-7xl px-6 py-4 flex justify-between">
                    <span>Chunk size: {{ round(config('upload.chunk_size') / 1048576, 2) }} MB</span>
                    <span>Image variants: {{ implode(', ', array_keys(config('upload.variants'))) }}</span>
                </div>
            </footer>
        </div>
        @stack('scripts')
    </body>
</html>
