<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Prediksi Penjualan Kripik Pisang - UD. Burno Sari</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
    </style>
</head>
<body class="bg-linear-to-br from-amber-50 via-white to-orange-50 text-slate-800 antialiased selection:bg-amber-500 selection:text-white min-h-screen flex flex-col justify-between">

    <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-amber-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-amber-500 rounded-xl flex items-center justify-center text-xl shadow-md shadow-amber-500/20">
                    🍌
                </div>
                <div>
                    <span class="text-lg font-extrabold text-slate-900 tracking-tight block leading-none">UD. BURNO SARI</span>
                    <span class="text-[10px] text-amber-700 font-bold tracking-wider uppercase">Kripik Pisang Premium</span>
                </div>
            </div>

            <nav>
                @auth
                    <a href="/backend" class="inline-flex items-center gap-2 text-sm font-bold bg-slate-900 text-white px-6 py-2.5 rounded-xl hover:bg-slate-800 shadow-lg shadow-slate-900/10 transition-all hover:-translate-y-0.5">
                        <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.003 9.003 0 1020.945 13H11V3.055z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>
                        </svg>
                        Backend
                    </a>
                @else
                    <a href="/backend/login" class="inline-flex items-center gap-2 text-sm font-bold bg-amber-500 text-white px-6 py-2.5 rounded-xl hover:bg-amber-600 shadow-md shadow-amber-500/20 transition-all hover:-translate-y-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                        </svg>
                        Login Sistem
                    </a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-20 grow flex items-center">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center w-full">
            
            <div class="space-y-6 text-center lg:text-left lg:col-span-7">
                <div class="inline-flex items-center gap-2 bg-linear-to-r from-amber-100 to-orange-100 text-amber-900 text-xs font-bold px-4 py-2 rounded-full shadow-sm">
                    ✨ Berbasis Web & Real-Time Data Analytics
                </div>
                
                <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black text-slate-900 tracking-tight leading-tight">
                    Sistem Prediksi <br class="hidden sm:block">
                    Tingkat Penjualan <br class="hidden sm:block">
                    <span class="text-transparent bg-clip-text bg-linear-to-r from-amber-500 to-orange-600">Kripik Pisang</span>
                </h1>
                
                <p class="text-base sm:text-lg text-slate-600 max-w-xl mx-auto lg:mx-0 leading-relaxed">
                    Optimasi stok dan produksi pada <strong class="text-slate-800">UD. Burno Sari</strong> menggunakan kecerdasan komputasi Peramalan Akurat Metode <span class="underline decoration-amber-500 decoration-2 font-semibold text-slate-800">Double Exponential Smoothing</span>.
                </p>

                <div class="pt-4 flex flex-wrap gap-3 justify-center lg:justify-start">
                    <span class="bg-white border border-slate-100 text-slate-700 px-4 py-2 rounded-xl text-xs font-semibold shadow-sm flex items-center gap-2">
                        📊 Trend Analysis
                    </span>
                    <span class="bg-white border border-slate-100 text-slate-700 px-4 py-2 rounded-xl text-xs font-semibold shadow-sm flex items-center gap-2">
                        📉 Low Error Rate (MAPE)
                    </span>
                    <span class="bg-white border border-slate-100 text-slate-700 px-4 py-2 rounded-xl text-xs font-semibold shadow-sm flex items-center gap-2">
                        📦 Manajemen Stok Efisien
                    </span>
                </div>
            </div>

            <div class="relative flex justify-center lg:justify-end lg:col-span-5 w-full max-w-md mx-auto lg:max-w-none">
                <div class="absolute inset-0 bg-linear-to-tr from-amber-400 to-orange-300 rounded-3xl filter blur-3xl opacity-20 -z-10 transform scale-95"></div>
                
                <div class="bg-white p-6 rounded-3xl shadow-2xl border border-slate-100 w-full space-y-6 transform hover:scale-[1.02] transition-transform duration-300">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Simulasi Hasil Peramalan</p>
                        </div>
                        <span class="text-[11px] bg-emerald-50 text-emerald-700 font-extrabold px-2.5 py-1 rounded-lg">Alpha (α) = 0.3</span>
                    </div>

                    <div class="h-44 flex items-end justify-between gap-3 pt-4 border-b border-slate-100 pb-2">
                        <div class="w-full bg-amber-100 rounded-t-lg h-24 relative group">
                            <div class="absolute -top-7 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-[10px] font-bold px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">120 kg</div>
                            <span class="absolute -bottom-6 left-1/2 -translate-x-1/2 text-[10px] font-bold text-slate-400">Mei</span>
                        </div>
                        <div class="w-full bg-amber-200 rounded-t-lg h-32 relative group">
                            <div class="absolute -top-7 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-[10px] font-bold px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">165 kg</div>
                            <span class="absolute -bottom-6 left-1/2 -translate-x-1/2 text-[10px] font-bold text-slate-400">Jun</span>
                        </div>
                        <div class="w-full bg-linear-to-t from-orange-500 to-amber-400 rounded-t-lg h-40 relative group shadow-md shadow-orange-500/20">
                            <div class="absolute -top-7 left-1/2 -translate-x-1/2 bg-orange-600 text-white text-[10px] font-bold px-1.5 py-0.5 rounded shadow whitespace-nowrap">198 kg (Prediksi)</div>
                            <span class="absolute -bottom-6 left-1/2 -translate-x-1/2 text-[10px] font-bold text-orange-600">Jul*</span>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 pt-2">
                        <div class="bg-amber-50/50 p-3 rounded-2xl border border-amber-100/50">
                            <p class="text-[10px] text-slate-400 font-medium">Tren Penjualan</p>
                            <p class="text-sm font-bold text-slate-800 flex items-center gap-1">
                                ▲ Meningkat
                            </p>
                        </div>
                        <div class="bg-orange-50/50 p-3 rounded-2xl border border-orange-100/50">
                            <p class="text-[10px] text-slate-400 font-medium">Rekomendasi Produksi</p>
                            <p class="text-sm font-bold text-orange-700">Naikkan Stok</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <footer class="border-t border-slate-100 bg-white py-6 w-full">
        <div class="max-w-7xl mx-auto px-4 text-center text-xs text-slate-400 font-medium">
            &copy; {{ date('Y') }} UD. Burno Sari. Pengolahan Data Penjualan Metode Double Exponential Smoothing.
        </div>
    </footer>

</body>
</html>