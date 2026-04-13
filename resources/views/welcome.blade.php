<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Mastermind HRMS — Enterprise Human Resource Management System. Manage employees, payroll, attendance, recruitment, performance and more in one unified platform.">
    <title>Mastermind HRMS — Enterprise Workforce Management</title>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    {{-- Icons --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    {{-- Tailwind CSS --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: {
                            50:  '#eff6ff',
                            100: '#dbeafe',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                            950: '#172554',
                        }
                    },
                    animation: {
                        'blob': 'blob 7s infinite',
                        'blob-slow': 'blob 10s infinite',
                        'float': 'float 6s ease-in-out infinite',
                        'float-slow': 'float 9s ease-in-out infinite',
                        'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'spin-slow': 'spin 20s linear infinite',
                        'gradient': 'gradient 8s ease infinite',
                        'slide-up': 'slideUp 0.6s ease forwards',
                        'bounce-soft': 'bounceSoft 2s ease-in-out infinite',
                    },
                    keyframes: {
                        blob: {
                            '0%, 100%': { transform: 'translate(0px, 0px) scale(1)' },
                            '33%':       { transform: 'translate(30px, -50px) scale(1.1)' },
                            '66%':       { transform: 'translate(-20px, 20px) scale(0.9)' },
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%':      { transform: 'translateY(-20px)' },
                        },
                        gradient: {
                            '0%':   { backgroundPosition: '0% 50%' },
                            '50%':  { backgroundPosition: '100% 50%' },
                            '100%': { backgroundPosition: '0% 50%' },
                        },
                        slideUp: {
                            from: { opacity: '0', transform: 'translateY(30px)' },
                            to:   { opacity: '1', transform: 'translateY(0)' },
                        },
                        bounceSoft: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%':      { transform: 'translateY(8px)' },
                        }
                    },
                    backdropBlur: { xs: '2px' },
                    backgroundSize: { '300%': '300%' },
                }
            }
        }
    </script>

    {{-- Alpine.js --}}
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        /* ─── Base ─────────────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { font-family: 'Inter', sans-serif; }
        body { overflow-x: hidden; background: #020617; }

        /* ─── Gradient Text ─────────────────────────────────────────── */
        .gradient-text {
            background: linear-gradient(135deg, #60a5fa 0%, #34d399 50%, #818cf8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            background-size: 200% auto;
            animation: textShine 4s linear infinite;
        }
        .gradient-text-blue {
            background: linear-gradient(135deg, #93c5fd 0%, #6366f1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        @keyframes textShine {
            to { background-position: 200% center; }
        }

        /* ─── Glassmorphism ──────────────────────────────────────────── */
        .glass {
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .glass-light {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.6);
        }
        .glass-card {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(148, 163, 184, 0.1);
        }

        /* ─── Animated Blobs ─────────────────────────────────────────── */
        .blob-1 { animation: blob 7s infinite; }
        .blob-2 { animation: blob 9s infinite; animation-delay: 2s; }
        .blob-3 { animation: blob 11s infinite; animation-delay: 4s; }
        @keyframes blob {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33%       { transform: translate(40px, -60px) scale(1.15); }
            66%       { transform: translate(-30px, 30px) scale(0.85); }
        }

        /* ─── Gradient Border ────────────────────────────────────────── */
        .gradient-border {
            background: linear-gradient(#0f172a, #0f172a) padding-box,
                        linear-gradient(135deg, #3b82f6, #8b5cf6, #06b6d4) border-box;
            border: 1.5px solid transparent;
        }
        .gradient-border-light {
            background: linear-gradient(white, white) padding-box,
                        linear-gradient(135deg, #3b82f6, #8b5cf6) border-box;
            border: 1.5px solid transparent;
        }

        /* ─── Scroll Reveal ──────────────────────────────────────────── */
        .reveal {
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.7s cubic-bezier(0.22, 1, 0.36, 1),
                        transform 0.7s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .reveal.visible  { opacity: 1; transform: translateY(0); }
        .reveal-left  { opacity: 0; transform: translateX(-40px); transition: opacity 0.7s ease, transform 0.7s ease; }
        .reveal-right { opacity: 0; transform: translateX(40px);  transition: opacity 0.7s ease, transform 0.7s ease; }
        .reveal-left.visible,
        .reveal-right.visible { opacity: 1; transform: translateX(0); }
        .reveal-delay-1 { transition-delay: 0.1s; }
        .reveal-delay-2 { transition-delay: 0.2s; }
        .reveal-delay-3 { transition-delay: 0.3s; }
        .reveal-delay-4 { transition-delay: 0.4s; }
        .reveal-delay-5 { transition-delay: 0.5s; }
        .reveal-delay-6 { transition-delay: 0.6s; }

        /* ─── Hover Effects ──────────────────────────────────────────── */
        .hover-lift {
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1),
                        box-shadow 0.3s ease;
        }
        .hover-lift:hover { transform: translateY(-6px); box-shadow: 0 24px 48px rgba(0,0,0,0.18); }
        .hover-glow:hover { box-shadow: 0 0 30px rgba(59, 130, 246, 0.3); }

        /* ─── Button Styles ──────────────────────────────────────────── */
        .btn-hero-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            padding: 14px 32px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.4);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-hero-primary:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            box-shadow: 0 8px 30px rgba(37, 99, 235, 0.6);
            transform: translateY(-2px);
        }
        .btn-hero-ghost {
            background: transparent;
            color: white;
            padding: 14px 32px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
            border: 1.5px solid rgba(255,255,255,0.25);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-hero-ghost:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.5);
            transform: translateY(-2px);
        }

        /* ─── Grid Background Pattern ────────────────────────────────── */
        .grid-pattern {
            background-image:
                linear-gradient(rgba(148,163,184,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148,163,184,0.03) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        /* ─── Dot Grid Pattern ────────────────────────────────────────── */
        .dot-pattern {
            background-image: radial-gradient(rgba(148,163,184,0.15) 1px, transparent 1px);
            background-size: 30px 30px;
        }

        /* ─── Navbar ──────────────────────────────────────────────────── */
        #navbar {
            transition: background 0.4s ease, box-shadow 0.4s ease, backdrop-filter 0.4s ease;
        }
        #navbar.scrolled {
            background: rgba(2, 6, 23, 0.92) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 1px 0 rgba(148,163,184,0.1);
        }

        /* ─── Module Tab ─────────────────────────────────────────────── */
        .module-tab { transition: all 0.2s ease; }
        .module-tab.active { background: linear-gradient(135deg, #1e40af, #4f46e5); color: white; }
        .module-tab:not(.active):hover { background: rgba(59,130,246,0.08); }

        /* ─── Mini Dashboard Preview ─────────────────────────────────── */
        .mini-bar { animation: barGrow 2s ease forwards; transform-origin: bottom; }
        @keyframes barGrow { from { transform: scaleY(0); } to { transform: scaleY(1); } }

        /* ─── Glow Ring ──────────────────────────────────────────────── */
        .glow-ring {
            box-shadow: 0 0 0 1px rgba(59,130,246,0.3),
                        0 0 20px rgba(59,130,246,0.15),
                        0 0 60px rgba(59,130,246,0.08);
        }

        /* ─── Tech Badge ─────────────────────────────────────────────── */
        .tech-badge {
            transition: all 0.3s ease;
            cursor: default;
        }
        .tech-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 16px rgba(59,130,246,0.2);
        }

        /* ─── Stat Card ──────────────────────────────────────────────── */
        .stat-counter { font-variant-numeric: tabular-nums; }

        /* ─── Scrollbar ──────────────────────────────────────────────── */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #020617; }
        ::-webkit-scrollbar-thumb { background: #1e40af; border-radius: 3px; }

        /* ─── Selection ──────────────────────────────────────────────── */
        ::selection { background: rgba(59,130,246,0.3); color: white; }

        /* ─── Responsive Typography ──────────────────────────────────── */
        .hero-title { font-size: clamp(2.5rem, 6vw, 5rem); font-weight: 900; line-height: 1.05; letter-spacing: -0.03em; }
        .section-title { font-size: clamp(1.8rem, 4vw, 2.75rem); font-weight: 800; letter-spacing: -0.02em; }

        /* ─── Floating Pill ──────────────────────────────────────────── */
        .pill-float { animation: pillFloat 4s ease-in-out infinite; }
        .pill-float-2 { animation: pillFloat 5s ease-in-out infinite; animation-delay: 1s; }
        .pill-float-3 { animation: pillFloat 6s ease-in-out infinite; animation-delay: 2s; }
        @keyframes pillFloat {
            0%, 100% { transform: translateY(0) rotate(-1deg); }
            50%       { transform: translateY(-10px) rotate(1deg); }
        }

        /* ─── Role Card ──────────────────────────────────────────────── */
        .role-card { transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .role-card:hover { transform: translateY(-8px) scale(1.01); }

        /* ─── Step Connector ─────────────────────────────────────────── */
        .step-line { background: linear-gradient(90deg, #3b82f6, #8b5cf6); height: 2px; }

        /* ─── Animate number count ───────────────────────────────────── */
        @keyframes countUp {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .counted { animation: countUp 0.4s ease forwards; }
    </style>
</head>
<body>

{{-- ══════════════════════════════════════════════════════════════════
     SECTION 1 — NAVIGATION
══════════════════════════════════════════════════════════════════════ --}}
<nav id="navbar" class="fixed top-0 left-0 right-0 z-50 px-4 sm:px-6 lg:px-8"
     x-data="{ mobileOpen: false }">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between h-16 sm:h-18">

            {{-- Logo --}}
            <a href="/" class="flex items-center gap-2.5 group">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-blue-500/30 group-hover:scale-110 transition-transform">
                    <i class="fas fa-building text-white text-sm"></i>
                </div>
                <div class="flex flex-col leading-none">
                    <span class="text-white font-bold text-base tracking-tight">Mastermind</span>
                    <span class="text-blue-400 text-xs font-medium tracking-widest uppercase">HRMS</span>
                </div>
            </a>

            {{-- Desktop Nav --}}
            <div class="hidden lg:flex items-center gap-1">
                <a href="#features" class="px-4 py-2 text-sm text-slate-300 hover:text-white hover:bg-white/5 rounded-lg transition-all font-medium">Features</a>
                <a href="#modules" class="px-4 py-2 text-sm text-slate-300 hover:text-white hover:bg-white/5 rounded-lg transition-all font-medium">Modules</a>
                <a href="#roles" class="px-4 py-2 text-sm text-slate-300 hover:text-white hover:bg-white/5 rounded-lg transition-all font-medium">Roles</a>
                <a href="#how-it-works" class="px-4 py-2 text-sm text-slate-300 hover:text-white hover:bg-white/5 rounded-lg transition-all font-medium">How It Works</a>
                <a href="#tech" class="px-4 py-2 text-sm text-slate-300 hover:text-white hover:bg-white/5 rounded-lg transition-all font-medium">Technology</a>
            </div>

            {{-- CTA Buttons --}}
            <div class="hidden lg:flex items-center gap-3">
                <a href="{{ route('login') }}" class="px-5 py-2 text-sm text-slate-300 hover:text-white font-medium transition-colors">Sign In</a>
                <a href="{{ route('login') }}" class="px-5 py-2 text-sm font-semibold bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white rounded-xl shadow-lg shadow-blue-500/25 transition-all hover:scale-105 hover:shadow-blue-500/40">
                    Get Started <i class="fas fa-arrow-right ml-1 text-xs"></i>
                </a>
            </div>

            {{-- Mobile Hamburger --}}
            <button @click="mobileOpen = !mobileOpen" class="lg:hidden p-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors">
                <i class="fas fa-bars text-lg" x-show="!mobileOpen"></i>
                <i class="fas fa-times text-lg" x-show="mobileOpen"></i>
            </button>
        </div>

        {{-- Mobile Menu --}}
        <div x-show="mobileOpen" x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="lg:hidden glass rounded-2xl border border-white/10 p-4 mb-3 space-y-1"
             @click.away="mobileOpen = false">
            <a href="#features" @click="mobileOpen=false" class="block px-4 py-3 text-sm text-slate-300 hover:text-white hover:bg-white/5 rounded-xl transition-all font-medium"><i class="fas fa-star mr-2 text-blue-400"></i> Features</a>
            <a href="#modules" @click="mobileOpen=false" class="block px-4 py-3 text-sm text-slate-300 hover:text-white hover:bg-white/5 rounded-xl transition-all font-medium"><i class="fas fa-th-large mr-2 text-blue-400"></i> Modules</a>
            <a href="#roles" @click="mobileOpen=false" class="block px-4 py-3 text-sm text-slate-300 hover:text-white hover:bg-white/5 rounded-xl transition-all font-medium"><i class="fas fa-users mr-2 text-blue-400"></i> Roles</a>
            <a href="#how-it-works" @click="mobileOpen=false" class="block px-4 py-3 text-sm text-slate-300 hover:text-white hover:bg-white/5 rounded-xl transition-all font-medium"><i class="fas fa-map mr-2 text-blue-400"></i> How It Works</a>
            <div class="pt-3 border-t border-white/10 flex gap-2">
                <a href="{{ route('login') }}" class="flex-1 text-center py-3 text-sm text-slate-300 hover:text-white font-medium rounded-xl hover:bg-white/5 transition-colors">Sign In</a>
                <a href="{{ route('login') }}" class="flex-1 text-center py-3 text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white rounded-xl transition-colors">Get Started</a>
            </div>
        </div>
    </div>
</nav>

{{-- ══════════════════════════════════════════════════════════════════
     SECTION 2 — HERO
══════════════════════════════════════════════════════════════════════ --}}
<section class="relative min-h-screen flex flex-col items-center justify-center overflow-hidden grid-pattern" style="background: radial-gradient(ellipse 80% 80% at 50% -10%, rgba(30,64,175,0.3) 0%, transparent 60%), linear-gradient(to bottom right, #020617, #0a0f2e, #020617);">

    {{-- Animated Blobs --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="blob-1 absolute top-1/4 left-1/4 w-96 h-96 rounded-full opacity-20" style="background: radial-gradient(circle, #3b82f6, #1e40af); filter: blur(80px);"></div>
        <div class="blob-2 absolute top-1/3 right-1/4 w-80 h-80 rounded-full opacity-15" style="background: radial-gradient(circle, #8b5cf6, #6d28d9); filter: blur(80px);"></div>
        <div class="blob-3 absolute bottom-1/4 left-1/3 w-72 h-72 rounded-full opacity-15" style="background: radial-gradient(circle, #06b6d4, #0284c7); filter: blur(80px);"></div>
        {{-- Stars / Dots --}}
        <div class="absolute inset-0" style="background-image: radial-gradient(rgba(255,255,255,0.08) 1px, transparent 1px); background-size: 50px 50px;"></div>
    </div>

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 pb-16 w-full">
        <div class="text-center mb-12">

            {{-- Badge chip --}}
            <div class="inline-flex items-center gap-2 glass px-4 py-2 rounded-full mb-8 border border-blue-500/30 reveal">
                <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                <span class="text-xs font-semibold text-blue-300 tracking-wider uppercase">🏆 Enterprise HRMS Platform</span>
                <span class="text-slate-500 text-xs">v2.0</span>
            </div>

            {{-- Main Headline --}}
            <h1 class="hero-title text-white mb-6 reveal reveal-delay-1">
                Manage Your Entire<br>
                Workforce —<br>
                <span class="gradient-text">All in One Place</span>
            </h1>

            {{-- Subheadline --}}
            <p class="text-lg sm:text-xl text-slate-400 max-w-2xl mx-auto mb-10 leading-relaxed font-light reveal reveal-delay-2">
                A complete enterprise HR solution with <strong class="text-slate-300 font-semibold">10 integrated modules</strong>, <strong class="text-slate-300 font-semibold">6 role-based access levels</strong>, automated payroll, AI-powered recruitment scoring, real-time attendance tracking, and beautiful analytics.
            </p>

            {{-- CTA Buttons --}}
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-16 reveal reveal-delay-3">
                <a href="{{ route('login') }}" class="btn-hero-primary">
                    <i class="fas fa-rocket"></i>
                    Get Started Free
                </a>
                <a href="#modules" class="btn-hero-ghost">
                    <i class="fas fa-play-circle"></i>
                    Explore Modules
                </a>
            </div>
        </div>

        {{-- Hero Dashboard Preview --}}
        <div class="relative max-w-4xl mx-auto reveal reveal-delay-4">

            {{-- Floating Pills --}}
            <div class="pill-float absolute -top-6 -left-4 z-20 hidden md:flex items-center gap-2 glass px-3 py-2 rounded-xl border border-green-500/30">
                <div class="w-6 h-6 rounded-full bg-green-500/20 flex items-center justify-center"><i class="fas fa-users text-green-400 text-xs"></i></div>
                <div><p class="text-xs text-green-400 font-semibold">247 Active</p><p class="text-xs text-slate-500">Employees</p></div>
            </div>
            <div class="pill-float-2 absolute -top-4 -right-6 z-20 hidden md:flex items-center gap-2 glass px-3 py-2 rounded-xl border border-blue-500/30">
                <div class="w-6 h-6 rounded-full bg-blue-500/20 flex items-center justify-center"><i class="fas fa-shield-alt text-blue-400 text-xs"></i></div>
                <div><p class="text-xs text-blue-400 font-semibold">6 Roles</p><p class="text-xs text-slate-500">RBAC System</p></div>
            </div>
            <div class="pill-float-3 absolute -bottom-6 -right-4 z-20 hidden md:flex items-center gap-2 glass px-3 py-2 rounded-xl border border-purple-500/30">
                <div class="w-6 h-6 rounded-full bg-purple-500/20 flex items-center justify-center"><i class="fas fa-check-circle text-purple-400 text-xs"></i></div>
                <div><p class="text-xs text-purple-400 font-semibold">99.9%</p><p class="text-xs text-slate-500">Uptime</p></div>
            </div>

            {{-- Main Dashboard Card --}}
            <div class="glass-card rounded-2xl p-1 glow-ring" style="background: rgba(10,15,46,0.8);">
                {{-- Fake Browser Bar --}}
                <div class="flex items-center gap-2 px-4 py-3 border-b border-white/5">
                    <div class="flex gap-1.5">
                        <div class="w-3 h-3 rounded-full bg-red-400/70"></div>
                        <div class="w-3 h-3 rounded-full bg-yellow-400/70"></div>
                        <div class="w-3 h-3 rounded-full bg-green-400/70"></div>
                    </div>
                    <div class="flex-1 mx-3">
                        <div class="bg-white/5 rounded-md px-3 py-1 text-xs text-slate-500 text-center max-w-xs mx-auto">
                            <i class="fas fa-lock text-green-400 mr-1 text-xs"></i> mastermind-hrms.com/dashboard
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    {{-- Fake Topbar --}}
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-white font-semibold text-sm">Good morning, Sarah ✨</p>
                            <p class="text-slate-500 text-xs">Monday, April 14, 2025</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-blue-600/20 flex items-center justify-center"><i class="fas fa-bell text-blue-400 text-xs"></i></div>
                            <div class="w-7 h-7 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-xs font-bold">S</div>
                        </div>
                    </div>

                    {{-- Stat Cards Row --}}
                    <div class="grid grid-cols-4 gap-3 mb-4">
                        @foreach([['247','Employees','users','blue'],['92%','Attendance','clock','green'],['12','Pending','calendar-minus','yellow'],['KSh 2.4M','Payroll','money-bill-wave','purple']] as [$val,$label,$icon,$color])
                        <div class="rounded-xl p-3" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06);">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-slate-500 font-medium">{{ $label }}</span>
                                <i class="fas fa-{{ $icon }} text-{{ $color }}-400 text-xs"></i>
                            </div>
                            <p class="text-white font-bold text-sm">{{ $val }}</p>
                            <div class="w-full bg-white/5 rounded-full h-1 mt-2">
                                <div class="bg-{{ $color }}-500 h-1 rounded-full" style="width: {{ rand(55,85) }}%"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    {{-- Mini Chart Area --}}
                    <div class="rounded-xl p-3" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs text-slate-400 font-semibold">7-Day Attendance</p>
                            <span class="text-xs text-green-400 font-medium">↑ 4.2%</span>
                        </div>
                        <div class="flex items-end gap-1.5 h-14">
                            @foreach([65,80,70,90,85,75,92] as $h)
                            <div class="flex-1 rounded-t" style="height: {{ $h }}%; background: linear-gradient(to top, #2563eb, #60a5fa); opacity: 0.8;"></div>
                            @endforeach
                        </div>
                        <div class="flex justify-between mt-1">
                            @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d)
                            <span class="text-xs text-slate-600">{{ $d }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Scroll Indicator --}}
        <div class="flex justify-center mt-12">
            <a href="#stats" class="flex flex-col items-center gap-2 text-slate-600 hover:text-slate-400 transition-colors group">
                <span class="text-xs font-medium tracking-wider uppercase">Scroll to explore</span>
                <i class="fas fa-chevron-down text-blue-400 animate-bounce group-hover:text-blue-300"></i>
            </a>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════════
     SECTION 3 — STATS BAR
══════════════════════════════════════════════════════════════════════ --}}
<section id="stats" class="py-16 relative overflow-hidden" style="background: linear-gradient(to right, #0f172a, #1e1b4b, #0f172a);">
    <div class="absolute inset-0 dot-pattern opacity-30"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-0 lg:divide-x lg:divide-white/10">
            @foreach([
                ['500', '+', 'Companies Trust Us', 'fa-building', 'from-blue-400 to-cyan-400'],
                ['10',  '',  'Integrated Modules', 'fa-th-large', 'from-purple-400 to-pink-400'],
                ['6',   '',  'User Access Roles',  'fa-shield-alt','from-green-400 to-teal-400'],
                ['99',  '.9%','System Uptime',     'fa-check-circle','from-yellow-400 to-orange-400'],
            ] as [$num, $suf, $label, $icon, $grad])
            <div class="text-center px-6 reveal">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl mb-4" style="background: rgba(59,130,246,0.12);">
                    <i class="fas {{ $icon }} text-blue-400 text-lg"></i>
                </div>
                <div class="flex items-baseline justify-center gap-0.5 mb-1">
                    <span class="text-4xl font-black stat-counter bg-gradient-to-r {{ $grad }} bg-clip-text text-transparent" data-target="{{ $num }}" data-suffix="{{ $suf }}">{{ $num }}{{ $suf }}</span>
                </div>
                <p class="text-slate-400 text-sm font-medium">{{ $label }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════════
     SECTION 4 — FEATURES
══════════════════════════════════════════════════════════════════════ --}}
<section id="features" class="py-24 relative" style="background: #020617;">
    <div class="absolute inset-0 grid-pattern opacity-40"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Section Header --}}
        <div class="text-center mb-16">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-blue-500/30 bg-blue-500/10 mb-5 reveal">
                <i class="fas fa-bolt text-blue-400 text-xs"></i>
                <span class="text-xs font-semibold text-blue-400 tracking-wider uppercase">Why Mastermind HRMS</span>
            </div>
            <h2 class="section-title text-white mb-4 reveal reveal-delay-1">
                Everything HR Needs,<br><span class="gradient-text-blue">Nothing It Doesn't</span>
            </h2>
            <p class="text-slate-400 text-lg max-w-2xl mx-auto font-light reveal reveal-delay-2">
                Purpose-built for East African enterprises. Every feature designed to eliminate friction and empower your HR team.
            </p>
        </div>

        {{-- Feature Cards Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach([
                ['fa-shield-alt','Role-Based Access Control','from-blue-500 to-blue-600','6 granular user roles with Spatie RBAC — from Super Admin to self-service Employee portal. Every action is permission-gated.','blue'],
                ['fa-chart-bar','Real-Time Analytics Dashboard','from-purple-500 to-indigo-600','Live ApexCharts dashboards with headcount trends, attendance heatmaps, payroll cost analysis, and KPI scorecards.','purple'],
                ['fa-money-bill-wave','Automated Payroll Engine','from-green-500 to-teal-600','Full payroll computation — basic salary, allowances, deductions, PAYE tax, NHIF, NSSF. One-click PDF payslips.','green'],
                ['fa-robot','AI Recruitment Scoring','from-orange-500 to-amber-600','Keyword intelligence automatically scores candidate CVs against job requirements. Rank applicants by fit score instantly.','orange'],
                ['fa-calendar-check','Smart Leave Management','from-pink-500 to-rose-600','Multi-level leave approval workflow, automatic balance deduction, carry-forward policies, and instant notifications.','pink'],
                ['fa-file-pdf','PDF & Excel Exports','from-cyan-500 to-blue-600','DomPDF-powered payslips, DomPDF HR letters, and Maatwebsite/Excel exports for attendance, payroll, and performance reports.','cyan'],
            ] as [$icon, $title, $grad, $desc, $color])
            <div class="group hover-lift hover-glow gradient-border rounded-2xl p-6 cursor-default reveal">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br {{ $grad }} flex items-center justify-center mb-5 shadow-lg group-hover:scale-110 transition-transform">
                    <i class="fas {{ $icon }} text-white text-lg"></i>
                </div>
                <h3 class="text-white font-bold text-lg mb-3">{{ $title }}</h3>
                <p class="text-slate-400 text-sm leading-relaxed">{{ $desc }}</p>
                <div class="mt-4 flex items-center gap-1 text-{{ $color }}-400 text-xs font-semibold opacity-0 group-hover:opacity-100 transition-opacity">
                    <span>Learn more</span>
                    <i class="fas fa-arrow-right text-xs"></i>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════════
     SECTION 5 — MODULES SHOWCASE
══════════════════════════════════════════════════════════════════════ --}}
<section id="modules" class="py-24 relative" style="background: linear-gradient(180deg, #020617 0%, #0a0f2e 50%, #020617 100%);">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"
         x-data="{
            activeModule: 0,
            modules: [
                {
                    name: 'Employees',
                    icon: 'fa-users',
                    color: 'blue',
                    gradient: 'from-blue-500 to-blue-700',
                    tagline: 'Complete Employee Lifecycle Management',
                    desc: 'Maintain comprehensive employee profiles with personal details, documents, employment history, and organizational charts. From onboarding to offboarding, every employee touchpoint is managed seamlessly.',
                    features: ['Full CRUD employee profiles with photo upload','Document vault (ID, certificates, contracts)','Employment history & position timeline','Department & designation org chart'],
                    preview: { stats: ['247 Active','18 New','3 Resigned','5 On Leave'], icon: 'fa-users', bars: [70,85,60,90,75] }
                },
                {
                    name: 'Attendance',
                    icon: 'fa-clock',
                    color: 'green',
                    gradient: 'from-green-500 to-teal-600',
                    tagline: 'Real-Time Workforce Time Tracking',
                    desc: 'Clock-in/clock-out with geo-location capture, shift management, overtime calculation, and automated absent marking. Bulk attendance import and comprehensive reporting.',
                    features: ['GPS-enabled clock-in / clock-out widget','Shift assignment & grace period control','Overtime hours auto-calculation','Holiday calendar & bulk import'],
                    preview: { stats: ['94% Today','12 Late','3 Absent','22h OT'], icon: 'fa-clock', bars: [94,88,90,85,92] }
                },
                {
                    name: 'Leave',
                    icon: 'fa-calendar-minus',
                    color: 'yellow',
                    gradient: 'from-yellow-500 to-orange-600',
                    tagline: 'Multi-Level Leave Approval Workflow',
                    desc: 'Configure unlimited leave types with carry-forward policies. Employees request, managers approve or reject, and balances update automatically. Email notifications keep everyone in sync.',
                    features: ['Unlimited custom leave types','Manager approval / rejection workflow','Auto balance deduction on approval','Annual carry-forward configuration'],
                    preview: { stats: ['8 Pending','24 Approved','5 Rejected','120 Remaining'], icon: 'fa-calendar-minus', bars: [30,80,20,90,60] }
                },
                {
                    name: 'Payroll',
                    icon: 'fa-money-bill-wave',
                    color: 'purple',
                    gradient: 'from-purple-500 to-indigo-700',
                    tagline: 'Automated Monthly Payroll Processing',
                    desc: 'Configure salary grades, allowances, and deductions once. Run payroll in one click — the engine computes gross, PAYE tax, NHIF, NSSF, and net pay for every employee, then generates beautiful PDF payslips.',
                    features: ['Salary grades & component library','One-click payroll run processing','PAYE / NHIF / NSSF calculation','DomPDF payslips with company branding'],
                    preview: { stats: ['KSh 2.4M','247 Slips','32% Tax','Net 1.6M'], icon: 'fa-money-bill-wave', bars: [100,85,68,65,72] }
                },
                {
                    name: 'Recruitment',
                    icon: 'fa-briefcase',
                    color: 'orange',
                    gradient: 'from-orange-500 to-red-600',
                    tagline: 'AI-Powered Talent Acquisition Pipeline',
                    desc: 'Post job openings, collect applications, and let our AI scoring engine rank candidates by keyword match against requirements. Track interviews, record feedback, and move top talent to hire.',
                    features: ['Job posting with rich description editor','AI keyword resume scoring (0–100%)','Interview scheduling & feedback logging','Pipeline: Applied → Screened → Hired'],
                    preview: { stats: ['12 Open Jobs','48 Candidates','8 Interviews','3 Offers'], icon: 'fa-briefcase', bars: [40,65,80,55,90] }
                },
                {
                    name: 'Performance',
                    icon: 'fa-chart-line',
                    color: 'indigo',
                    gradient: 'from-indigo-500 to-purple-700',
                    tagline: 'BSC KPI Framework & 360° Reviews',
                    desc: 'Define KPIs across the four BSC perspectives (Financial, Customer, Process, Learning). Run performance cycles, collect self/manager/peer reviews, and generate aggregated score reports.',
                    features: ['BSC KPI library with weights','Performance cycle management','Self, manager & peer review types','360° aggregated score reporting'],
                    preview: { stats: ['24 Reviews','87 Avg Score','3 Cycles','12 KPIs'], icon: 'fa-chart-line', bars: [87,92,78,85,90] }
                },
                {
                    name: 'Training',
                    icon: 'fa-graduation-cap',
                    color: 'teal',
                    gradient: 'from-teal-500 to-cyan-600',
                    tagline: 'Employee Learning & Development Hub',
                    desc: 'Build a course library, enroll employees, track completion progress, and manage professional certifications with expiry alerts. Keep your team skilled and certified.',
                    features: ['Course library with material uploads','Employee enrollment & progress tracking','Certification management with expiry alerts','Category-based training reports'],
                    preview: { stats: ['18 Courses','84 Enrolled','62% Done','24 Certs'], icon: 'fa-graduation-cap', bars: [62,75,80,55,90] }
                },
                {
                    name: 'Meetings',
                    icon: 'fa-video',
                    color: 'pink',
                    gradient: 'from-pink-500 to-rose-600',
                    tagline: 'Collaborative Meeting Scheduler & Calendar',
                    desc: 'Schedule meetings, invite participants, track RSVPs, and view everything on a FullCalendar.js interactive calendar. Participants accept or decline with one click.',
                    features: ['Meeting scheduler with location field','Participant invite & RSVP tracking','Accept / decline with one click','FullCalendar monthly / weekly view'],
                    preview: { stats: ['5 Today','12 This Week','3 Pending RSVPs','8 Rooms'], icon: 'fa-video', bars: [50,70,40,80,60] }
                },
                {
                    name: 'Reports',
                    icon: 'fa-file-alt',
                    color: 'cyan',
                    gradient: 'from-cyan-500 to-blue-600',
                    tagline: 'Comprehensive HR Analytics & Exports',
                    desc: 'Six dedicated reports — Employee, Attendance, Leave, Payroll, Performance, and Training — each with date-range filters, department drilldowns, and Excel export capability.',
                    features: ['6 pre-built HR report modules','Date range & department filters','Excel export via Maatwebsite','Printable PDF versions'],
                    preview: { stats: ['6 Reports','Excel Export','PDF Print','Chart View'], icon: 'fa-file-alt', bars: [90,85,80,95,88] }
                },
                {
                    name: 'Admin',
                    icon: 'fa-cog',
                    color: 'slate',
                    gradient: 'from-slate-500 to-slate-700',
                    tagline: 'System Administration & Configuration',
                    desc: 'Full control over users, roles, departments, designations, and system settings. Configure payroll rates, leave policies, notification preferences, and company branding from one panel.',
                    features: ['User management & role assignment','Department & designation structure','System settings (payroll, leave, notify)','Audit log for all critical actions'],
                    preview: { stats: ['8 Users','6 Roles','12 Depts','24 Settings'], icon: 'fa-cog', bars: [100,95,85,90,100] }
                }
            ]
         }">

        {{-- Section Header --}}
        <div class="text-center mb-16">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-purple-500/30 bg-purple-500/10 mb-5 reveal">
                <i class="fas fa-th-large text-purple-400 text-xs"></i>
                <span class="text-xs font-semibold text-purple-400 tracking-wider uppercase">Full Module Suite</span>
            </div>
            <h2 class="section-title text-white mb-4 reveal reveal-delay-1">
                10 Powerful Modules.<br><span class="gradient-text">One Unified Platform.</span>
            </h2>
            <p class="text-slate-400 text-lg max-w-xl mx-auto font-light reveal reveal-delay-2">
                Every HR process, from hire to retire, handled by purpose-built modules that talk to each other.
            </p>
        </div>

        {{-- Module Tab Switcher --}}
        <div class="glass-card rounded-2xl overflow-hidden border border-white/8 reveal">
            {{-- Tab Bar --}}
            <div class="flex overflow-x-auto scrollbar-hide border-b border-white/8 bg-white/2">
                <template x-for="(mod, i) in modules" :key="i">
                    <button @click="activeModule = i"
                            :class="activeModule === i ? 'border-b-2 border-blue-500 text-white bg-blue-500/10' : 'text-slate-400 hover:text-white hover:bg-white/5'"
                            class="flex items-center gap-2 px-4 py-4 text-xs font-semibold whitespace-nowrap transition-all border-b-2 border-transparent">
                        <i :class="'fas ' + mod.icon + ' text-sm'"></i>
                        <span x-text="mod.name"></span>
                    </button>
                </template>
            </div>

            {{-- Tab Content --}}
            <div class="p-6 lg:p-10">
                <template x-for="(mod, i) in modules" :key="i">
                    <div x-show="activeModule === i" x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-y-4"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-start">

                        {{-- Left: Info --}}
                        <div>
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl mb-6 shadow-xl"
                                 :style="'background: linear-gradient(135deg, var(--c1), var(--c2))'"
                                 :class="'bg-gradient-to-br ' + mod.gradient">
                                <i :class="'fas ' + mod.icon + ' text-white text-2xl'"></i>
                            </div>
                            <p class="text-blue-400 text-xs font-semibold tracking-wider uppercase mb-2" x-text="mod.tagline"></p>
                            <h3 class="text-white text-2xl font-bold mb-4" x-text="mod.name + ' Module'"></h3>
                            <p class="text-slate-400 text-base leading-relaxed mb-8" x-text="mod.desc"></p>
                            <div class="space-y-3">
                                <template x-for="feat in mod.features" :key="feat">
                                    <div class="flex items-start gap-3">
                                        <div class="w-5 h-5 rounded-full bg-blue-500/20 flex items-center justify-center flex-shrink-0 mt-0.5">
                                            <i class="fas fa-check text-blue-400 text-xs"></i>
                                        </div>
                                        <span class="text-slate-300 text-sm font-medium" x-text="feat"></span>
                                    </div>
                                </template>
                            </div>
                            <div class="mt-8">
                                <a href="{{ route('login') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600/20 hover:bg-blue-600/30 text-blue-400 text-sm font-semibold transition-all border border-blue-500/30 hover:border-blue-500/50">
                                    Access Module <i class="fas fa-arrow-right text-xs"></i>
                                </a>
                            </div>
                        </div>

                        {{-- Right: Mini Preview --}}
                        <div class="glass rounded-2xl p-6 border border-white/8">
                            <div class="flex items-center justify-between mb-5">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-lg flex items-center justify-center"
                                         :class="'bg-gradient-to-br ' + mod.gradient">
                                        <i :class="'fas ' + mod.icon + ' text-white text-xs'"></i>
                                    </div>
                                    <span class="text-white text-sm font-semibold" x-text="mod.name + ' Overview'"></span>
                                </div>
                                <span class="text-xs text-green-400 font-medium">● Live</span>
                            </div>
                            {{-- Mini Stats --}}
                            <div class="grid grid-cols-2 gap-3 mb-5">
                                <template x-for="stat in mod.preview.stats" :key="stat">
                                    <div class="p-3 rounded-xl" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06);">
                                        <p class="text-white text-sm font-bold" x-text="stat"></p>
                                    </div>
                                </template>
                            </div>
                            {{-- Mini Bar Chart --}}
                            <div>
                                <p class="text-slate-500 text-xs mb-2 font-medium">Monthly Trend</p>
                                <div class="flex items-end gap-1.5 h-20">
                                    <template x-for="(bar, bi) in mod.preview.bars" :key="bi">
                                        <div class="flex-1 rounded-t transition-all duration-500"
                                             :style="'height: ' + bar + '%; background: linear-gradient(to top, #1e40af, #60a5fa); opacity: ' + (0.5 + bi*0.1)"></div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════════
     SECTION 6 — ROLE-BASED ACCESS
══════════════════════════════════════════════════════════════════════ --}}
<section id="roles" class="py-24 relative" style="background: #030712;">
    <div class="absolute inset-0" style="background: radial-gradient(ellipse 60% 40% at 50% 50%, rgba(30,64,175,0.08) 0%, transparent 70%);"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="text-center mb-16">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-green-500/30 bg-green-500/10 mb-5 reveal">
                <i class="fas fa-users-cog text-green-400 text-xs"></i>
                <span class="text-xs font-semibold text-green-400 tracking-wider uppercase">Role-Based Access</span>
            </div>
            <h2 class="section-title text-white mb-4 reveal reveal-delay-1">
                Built for Every Role in<br><span class="gradient-text-blue">Your Organization</span>
            </h2>
            <p class="text-slate-400 text-lg max-w-xl mx-auto font-light reveal reveal-delay-2">
                Granular permissions ensure every user sees exactly what they need — nothing more, nothing less.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach([
                ['Super Admin','fa-crown','from-purple-500 to-violet-700','bg-purple-500/20 text-purple-300','border-purple-500/20',['Full system administration','All modules & settings access','User role assignment & audit logs']],
                ['HR Admin','fa-user-tie','from-blue-500 to-indigo-700','bg-blue-500/20 text-blue-300','border-blue-500/20',['All HR & payroll modules','Employee lifecycle management','Reports & analytics access']],
                ['Manager','fa-sitemap','from-green-500 to-teal-700','bg-green-500/20 text-green-300','border-green-500/20',['Approve leave & attendance','View team performance & KPIs','Access department reports']],
                ['Payroll Officer','fa-calculator','from-yellow-500 to-amber-700','bg-yellow-500/20 text-yellow-300','border-yellow-500/20',['Process & approve payroll runs','Download payslips & salary reports','Configure salary components']],
                ['Recruiter','fa-search','from-orange-500 to-red-700','bg-orange-500/20 text-orange-300','border-orange-500/20',['Manage job postings & candidates','Schedule & record interviews','Track AI-scored applications']],
                ['Employee','fa-user','from-slate-500 to-slate-700','bg-slate-500/20 text-slate-300','border-slate-500/20',['Clock in / out & view attendance','Apply for leave, view balance','Download own payslips']],
            ] as [$role, $icon, $grad, $badgeCls, $borderCls, $caps])
            <div class="role-card group gradient-border rounded-2xl p-6 cursor-default reveal hover-glow">
                <div class="flex items-start justify-between mb-5">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br {{ $grad }} flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                        <i class="fas {{ $icon }} text-white text-lg"></i>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $badgeCls }} border {{ $borderCls }}">{{ $role }}</span>
                </div>
                <h3 class="text-white font-bold text-lg mb-4">{{ $role }}</h3>
                <ul class="space-y-2 mb-5">
                    @foreach($caps as $cap)
                    <li class="flex items-center gap-2 text-sm text-slate-400">
                        <i class="fas fa-check-circle text-green-400 text-xs flex-shrink-0"></i>
                        {{ $cap }}
                    </li>
                    @endforeach
                </ul>
                <a href="{{ route('login') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-400 hover:text-blue-300 transition-colors opacity-0 group-hover:opacity-100">
                    Access as {{ $role }} <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════════
     SECTION 7 — HOW IT WORKS
══════════════════════════════════════════════════════════════════════ --}}
<section id="how-it-works" class="py-24 relative overflow-hidden" style="background: linear-gradient(180deg, #030712 0%, #0a0f2e 100%);">
    <div class="absolute inset-0 grid-pattern opacity-30"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="text-center mb-20">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-cyan-500/30 bg-cyan-500/10 mb-5 reveal">
                <i class="fas fa-rocket text-cyan-400 text-xs"></i>
                <span class="text-xs font-semibold text-cyan-400 tracking-wider uppercase">Quick Setup</span>
            </div>
            <h2 class="section-title text-white mb-4 reveal reveal-delay-1">
                Up and Running<br><span class="gradient-text">in Minutes</span>
            </h2>
            <p class="text-slate-400 text-lg max-w-lg mx-auto font-light reveal reveal-delay-2">
                Three simple steps separate you from a fully operational HR system.
            </p>
        </div>

        {{-- Steps --}}
        <div class="relative">
            {{-- Connecting Line (desktop) --}}
            <div class="hidden lg:block absolute top-16 left-1/4 right-1/4 h-px" style="background: linear-gradient(90deg, transparent, #3b82f6, #8b5cf6, #06b6d4, transparent); opacity: 0.4;"></div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-12 lg:gap-8">
                @foreach([
                    ['01','Setup Users & Roles','fa-users-cog','from-blue-500 to-indigo-600','Add your employees to the system, assign them roles, and configure department structure. The smart onboarding wizard guides you step by step.','blue'],
                    ['02','Configure Modules','fa-sliders-h','from-purple-500 to-pink-600','Enable the modules your organization needs. Set leave policies, payroll rates, shift schedules, KPI frameworks, and notification preferences.','purple'],
                    ['03','Go Live & Track','fa-chart-line','from-cyan-500 to-teal-600','Launch and watch your HR operations unfold in real time. Track attendance, process payroll, review performance — all from one dashboard.','cyan'],
                ] as [$step, $title, $icon, $grad, $desc, $color])
                <div class="relative text-center reveal reveal-delay-{{ $step === '01' ? 1 : ($step === '02' ? 2 : 3) }}">
                    {{-- Step Number --}}
                    <div class="relative inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br {{ $grad }} mb-6 shadow-2xl">
                        <span class="text-white font-black text-xl">{{ $step }}</span>
                        <div class="absolute -inset-1 rounded-2xl bg-gradient-to-br {{ $grad }} opacity-20 blur-md"></div>
                    </div>
                    {{-- Icon Ring --}}
                    <div class="flex justify-center mb-5">
                        <div class="w-12 h-12 rounded-2xl glass flex items-center justify-center border border-white/10">
                            <i class="fas {{ $icon }} text-{{ $color }}-400 text-lg"></i>
                        </div>
                    </div>
                    <h3 class="text-white text-xl font-bold mb-3">{{ $title }}</h3>
                    <p class="text-slate-400 text-sm leading-relaxed max-w-sm mx-auto">{{ $desc }}</p>
                </div>
                @endforeach
            </div>
        </div>

        {{-- CTA under steps --}}
        <div class="text-center mt-16 reveal">
            <a href="{{ route('login') }}" class="btn-hero-primary mx-auto inline-flex">
                <i class="fas fa-play"></i> Start Now — It's Free
            </a>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════════
     SECTION 8 — TECH STACK
══════════════════════════════════════════════════════════════════════ --}}
<section id="tech" class="py-24 relative" style="background: #020617;">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="text-center mb-16">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-slate-600/50 bg-slate-800/50 mb-5 reveal">
                <i class="fas fa-code text-slate-400 text-xs"></i>
                <span class="text-xs font-semibold text-slate-400 tracking-wider uppercase">Technology Stack</span>
            </div>
            <h2 class="section-title text-white mb-4 reveal reveal-delay-1">
                Built with<br><span class="gradient-text">World-Class Technology</span>
            </h2>
        </div>

        {{-- Tech Badges --}}
        <div class="flex flex-wrap justify-center gap-4 mb-16">
            @foreach([
                ['Laravel 11','fa-laravel','text-red-400','bg-red-500/10 border-red-500/20'],
                ['PHP 8.3','fa-php','text-indigo-400','bg-indigo-500/10 border-indigo-500/20'],
                ['MySQL 8','fa-database','text-blue-400','bg-blue-500/10 border-blue-500/20'],
                ['Tailwind CSS','fa-palette','text-cyan-400','bg-cyan-500/10 border-cyan-500/20'],
                ['Alpine.js','fa-mountain','text-green-400','bg-green-500/10 border-green-500/20'],
                ['jQuery 3','fa-js','text-yellow-400','bg-yellow-500/10 border-yellow-500/20'],
                ['ApexCharts','fa-chart-area','text-orange-400','bg-orange-500/10 border-orange-500/20'],
                ['DomPDF','fa-file-pdf','text-rose-400','bg-rose-500/10 border-rose-500/20'],
                ['Spatie RBAC','fa-shield-alt','text-purple-400','bg-purple-500/10 border-purple-500/20'],
                ['Select2','fa-search','text-teal-400','bg-teal-500/10 border-teal-500/20'],
            ] as [$name, $icon, $textCls, $bgCls])
            <div class="tech-badge flex items-center gap-2.5 px-5 py-3 rounded-xl border {{ $bgCls }} reveal">
                <i class="fab {{ $icon }} {{ $textCls }} text-base"></i>
                <span class="text-white text-sm font-semibold">{{ $name }}</span>
            </div>
            @endforeach
        </div>

        {{-- Trust Badges --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach([
                ['fa-lock','Secure Authentication','Role-based access with Spatie Laravel Permission. Every route and action is permission-gated.','from-blue-500/10 to-indigo-500/10','border-blue-500/20'],
                ['fa-bolt','High Performance','Eager-loaded queries, Redis-ready caching, and paginated results ensure sub-100ms response times.','from-yellow-500/10 to-orange-500/10','border-yellow-500/20'],
                ['fa-mobile-alt','Fully Responsive','Built with Tailwind CSS utility-first approach — flawless on desktop, tablet, and mobile devices.','from-green-500/10 to-teal-500/10','border-green-500/20'],
            ] as [$icon, $title, $desc, $grad, $border])
            <div class="p-6 rounded-2xl bg-gradient-to-br {{ $grad }} border {{ $border }} reveal hover-lift">
                <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center mb-4">
                    <i class="fas {{ $icon }} text-blue-400 text-lg"></i>
                </div>
                <h3 class="text-white font-bold text-base mb-2">{{ $title }}</h3>
                <p class="text-slate-400 text-sm leading-relaxed">{{ $desc }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════════
     SECTION 9 — CTA SECTION
══════════════════════════════════════════════════════════════════════ --}}
<section class="py-24 relative overflow-hidden">
    {{-- Background --}}
    <div class="absolute inset-0" style="background: linear-gradient(135deg, #1e40af 0%, #4f46e5 50%, #7c3aed 100%);"></div>
    <div class="absolute inset-0 grid-pattern opacity-30"></div>

    {{-- Decorative circles --}}
    <div class="absolute -top-20 -left-20 w-96 h-96 rounded-full opacity-20" style="background: radial-gradient(circle, white, transparent); filter: blur(40px);"></div>
    <div class="absolute -bottom-20 -right-20 w-96 h-96 rounded-full opacity-10" style="background: radial-gradient(circle, white, transparent); filter: blur(40px);"></div>

    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/15 border border-white/30 mb-8 reveal">
            <span class="w-2 h-2 rounded-full bg-green-300 animate-pulse"></span>
            <span class="text-xs font-semibold text-white/90 tracking-wider uppercase">System Available Now</span>
        </div>
        <h2 class="hero-title text-white mb-6 reveal reveal-delay-1">
            Ready to Transform<br>Your HR?
        </h2>
        <p class="text-xl text-blue-100/80 max-w-xl mx-auto mb-10 font-light leading-relaxed reveal reveal-delay-2">
            Join organizations across East Africa managing their workforce smarter, faster, and more efficiently with Mastermind HRMS.
        </p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 reveal reveal-delay-3">
            <a href="{{ route('login') }}" class="flex items-center gap-2 px-8 py-4 bg-white text-blue-800 font-bold text-base rounded-2xl shadow-2xl hover:shadow-white/25 hover:scale-105 transition-all">
                <i class="fas fa-rocket"></i>
                Access the System →
            </a>
            <a href="#modules" class="flex items-center gap-2 px-8 py-4 bg-white/10 text-white font-semibold text-base rounded-2xl border border-white/25 hover:bg-white/20 transition-all">
                <i class="fas fa-eye"></i>
                Explore Features
            </a>
        </div>
        <p class="text-blue-200/60 text-sm mt-8 reveal reveal-delay-4">
            <i class="fas fa-check-circle mr-1 text-green-300"></i> No credit card required &nbsp;·&nbsp;
            <i class="fas fa-check-circle mr-1 text-green-300"></i> Full feature access &nbsp;·&nbsp;
            <i class="fas fa-check-circle mr-1 text-green-300"></i> Enterprise ready
        </p>
    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════════
     SECTION 10 — FOOTER
══════════════════════════════════════════════════════════════════════ --}}
<footer style="background: #020617; border-top: 1px solid rgba(148,163,184,0.08);">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 mb-12">

            {{-- Col 1: Brand --}}
            <div class="lg:col-span-1">
                <div class="flex items-center gap-2.5 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg">
                        <i class="fas fa-building text-white text-sm"></i>
                    </div>
                    <div class="flex flex-col leading-none">
                        <span class="text-white font-bold text-base">Mastermind</span>
                        <span class="text-blue-400 text-xs font-medium tracking-widest uppercase">HRMS</span>
                    </div>
                </div>
                <p class="text-slate-500 text-sm leading-relaxed mb-6">
                    The complete enterprise HR management system for modern organizations. Built with Laravel 11.
                </p>
                <div class="flex items-center gap-3">
                    @foreach(['fa-twitter','fa-linkedin','fa-github','fa-envelope'] as $social)
                    <a href="#" class="w-8 h-8 rounded-lg bg-white/5 hover:bg-blue-600/20 flex items-center justify-center text-slate-500 hover:text-blue-400 transition-all border border-white/5 hover:border-blue-500/30">
                        <i class="fab {{ $social }} text-xs"></i>
                    </a>
                    @endforeach
                </div>
            </div>

            {{-- Col 2: System --}}
            <div>
                <h4 class="text-white font-semibold text-sm mb-4 tracking-wide">System</h4>
                <ul class="space-y-2.5">
                    @foreach([['fa-sign-in-alt','Login',route('login')],['fa-tachometer-alt','Dashboard','#'],['fa-book','Documentation','#'],['fa-question-circle','Support','#'],['fa-bug','Report Issue','#']] as [$icon,$label,$href])
                    <li>
                        <a href="{{ $href }}" class="flex items-center gap-2.5 text-slate-500 hover:text-white text-sm transition-colors group">
                            <i class="fas {{ $icon }} text-xs text-slate-600 group-hover:text-blue-400 transition-colors w-3"></i>
                            {{ $label }}
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>

            {{-- Col 3: Modules --}}
            <div>
                <h4 class="text-white font-semibold text-sm mb-4 tracking-wide">Modules</h4>
                <ul class="space-y-2.5">
                    @foreach([['fa-users','Employees'],['fa-clock','Attendance'],['fa-calendar-minus','Leave Management'],['fa-money-bill-wave','Payroll'],['fa-briefcase','Recruitment'],['fa-chart-line','Performance'],['fa-graduation-cap','Training'],['fa-video','Meetings']] as [$icon,$mod])
                    <li>
                        <a href="{{ route('login') }}" class="flex items-center gap-2.5 text-slate-500 hover:text-white text-sm transition-colors group">
                            <i class="fas {{ $icon }} text-xs text-slate-600 group-hover:text-blue-400 transition-colors w-3"></i>
                            {{ $mod }}
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>

            {{-- Col 4: Contact --}}
            <div>
                <h4 class="text-white font-semibold text-sm mb-4 tracking-wide">Contact</h4>
                <ul class="space-y-3">
                    <li class="flex items-start gap-3 text-sm">
                        <i class="fas fa-building text-blue-400 mt-0.5 text-xs w-3"></i>
                        <span class="text-slate-500">Mastermind Consultants Ltd<br>Nairobi, Kenya</span>
                    </li>
                    <li class="flex items-center gap-3 text-sm">
                        <i class="fas fa-envelope text-blue-400 text-xs w-3"></i>
                        <a href="mailto:hr@mastermind.co.ke" class="text-slate-500 hover:text-white transition-colors">hr@mastermind.co.ke</a>
                    </li>
                    <li class="flex items-center gap-3 text-sm">
                        <i class="fas fa-phone text-blue-400 text-xs w-3"></i>
                        <span class="text-slate-500">+254 700 000 000</span>
                    </li>
                    <li class="flex items-center gap-3 text-sm">
                        <i class="fas fa-globe text-blue-400 text-xs w-3"></i>
                        <span class="text-slate-500">mastermind.co.ke</span>
                    </li>
                </ul>
                <div class="mt-5 p-3 rounded-xl bg-green-500/10 border border-green-500/20">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></div>
                        <span class="text-green-400 text-xs font-semibold">System Online</span>
                    </div>
                    <p class="text-slate-500 text-xs mt-1">All services operational</p>
                </div>
            </div>
        </div>

        {{-- Footer Bottom --}}
        <div class="border-t border-white/5 pt-8 flex flex-col md:flex-row items-center justify-between gap-4">
            <p class="text-slate-600 text-sm">
                © {{ date('Y') }} Mastermind Consultants Ltd. All rights reserved.
            </p>
            <div class="flex items-center gap-1 text-slate-600 text-sm">
                <span>Built with</span>
                <i class="fas fa-heart text-red-500 mx-1 text-xs animate-pulse"></i>
                <span>using</span>
                <span class="text-red-400 font-semibold ml-1">Laravel 11</span>
            </div>
            <div class="flex items-center gap-4 text-xs text-slate-600">
                <a href="#" class="hover:text-white transition-colors">Privacy Policy</a>
                <a href="#" class="hover:text-white transition-colors">Terms of Service</a>
                <a href="{{ route('login') }}" class="hover:text-white transition-colors text-blue-500">Admin Login →</a>
            </div>
        </div>
    </div>
</footer>

{{-- ══════════════════════════════════════════════════════════════════
     JAVASCRIPT
══════════════════════════════════════════════════════════════════════ --}}
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── 1. Navbar scroll effect ────────────────────────────────────────────
    const navbar = document.getElementById('navbar');
    function updateNavbar() {
        if (window.scrollY > 60) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    }
    window.addEventListener('scroll', updateNavbar, { passive: true });
    updateNavbar();

    // ── 2. Scroll Reveal (Intersection Observer) ───────────────────────────
    const revealObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    document.querySelectorAll('.reveal, .reveal-left, .reveal-right').forEach(function (el) {
        revealObserver.observe(el);
    });

    // ── 3. Animated Stat Counters ──────────────────────────────────────────
    const counters = document.querySelectorAll('.stat-counter');
    const counterObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                const el       = entry.target;
                const target   = parseInt(el.getAttribute('data-target'), 10);
                const suffix   = el.getAttribute('data-suffix') || '';
                const duration = 2000;
                const startTime = performance.now();

                function update(currentTime) {
                    const elapsed  = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    // Ease out cubic
                    const eased    = 1 - Math.pow(1 - progress, 3);
                    const current  = Math.floor(eased * target);
                    el.textContent = current + (progress < 1 ? '' : suffix);
                    if (progress < 1) {
                        requestAnimationFrame(update);
                    }
                }
                requestAnimationFrame(update);
                counterObserver.unobserve(el);
            }
        });
    }, { threshold: 0.5 });

    counters.forEach(function (counter) {
        counterObserver.observe(counter);
    });

    // ── 4. Smooth Scroll for anchor links ─────────────────────────────────
    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href === '#') return;
            const target = document.querySelector(href);
            if (target) {
                e.preventDefault();
                const offset = 72; // navbar height
                const top    = target.getBoundingClientRect().top + window.scrollY - offset;
                window.scrollTo({ top: top, behavior: 'smooth' });
            }
        });
    });

    // ── 5. Staggered reveal delays ─────────────────────────────────────────
    // Already handled by CSS classes (.reveal-delay-1 through .reveal-delay-6)

    // ── 6. Lazy-init module tab reveal when section visible ────────────────
    const modulesSection = document.getElementById('modules');
    if (modulesSection) {
        const modObserver = new IntersectionObserver(function (entries) {
            if (entries[0].isIntersecting) {
                modulesSection.querySelector('.glass-card').classList.add('visible');
                modObserver.disconnect();
            }
        }, { threshold: 0.1 });
        modObserver.observe(modulesSection);
    }

    // ── 7. Parallax on hero blobs (subtle) ────────────────────────────────
    const blobs = document.querySelectorAll('.blob-1, .blob-2, .blob-3');
    window.addEventListener('mousemove', function (e) {
        const xPct = (e.clientX / window.innerWidth  - 0.5) * 20;
        const yPct = (e.clientY / window.innerHeight - 0.5) * 20;
        blobs.forEach(function (blob, i) {
            const factor = (i + 1) * 0.3;
            blob.style.transform = 'translate(' + (xPct * factor) + 'px, ' + (yPct * factor) + 'px)';
        });
    }, { passive: true });

});
</script>

</body>
</html>