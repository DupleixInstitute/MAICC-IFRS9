<template>
    <div class="flex min-h-screen overflow-hidden bg-gray-50">
        <!-- ============================ LEFT / BRAND PANEL ============================ -->
        <div class="relative hidden w-0 flex-1 lg:flex lg:flex-col justify-between overflow-hidden bg-gray-50 px-12 py-12 xl:px-16">
            <!-- faint hexagon watermark, top-right -->
            <svg class="pointer-events-none absolute -right-16 -top-16 h-80 w-80 text-maiicgold-400/20" viewBox="0 0 100 100" fill="none" aria-hidden="true">
                <polygon points="50,3 93,27 93,73 50,97 7,73 7,27" stroke="currentColor" stroke-width="1.2" fill="none"/>
                <polygon points="50,18 80,35 80,65 50,82 20,65 20,35" stroke="currentColor" stroke-width="1.2" fill="none"/>
            </svg>

            <!-- logo -->
            <div class="relative z-10">
                <img src="/images/maiic-logo.png" alt="MAIIC — Malawi Agricultural & Industrial Investment Corporation plc" class="h-20 w-auto"/>
            </div>

            <!-- headline -->
            <div class="relative z-10 max-w-xl">
                <h1 class="text-4xl font-extrabold leading-[1.15] tracking-tight text-gray-800 xl:text-5xl">
                    Investing in Potential.<br/>
                    <span class="text-maiic-600">Building Futures.</span>
                </h1>
                <!-- brand accent bar -->
                <div class="mt-6 flex h-1.5 w-40 overflow-hidden rounded-full">
                    <span class="flex-1 bg-maiic-600"></span>
                    <span class="flex-1 bg-maiicgold-500"></span>
                    <span class="flex-1 bg-red-600"></span>
                </div>
                <p class="mt-7 max-w-md text-lg leading-relaxed text-gray-600">
                    Empowering businesses across all sectors for a sustainable and prosperous Malawi.
                </p>
            </div>

            <!-- green wave + sector line-icons anchored bottom-left -->
            <div class="pointer-events-none absolute inset-x-0 bottom-0 z-0">
                <svg class="block h-64 w-full" viewBox="0 0 800 260" fill="none" preserveAspectRatio="none" aria-hidden="true">
                    <path d="M0 120 C 180 40, 340 200, 520 150 C 660 110, 740 200, 800 170 L 800 260 L 0 260 Z" fill="#166534"/>
                    <path d="M0 150 C 180 80, 340 230, 520 180 C 660 140, 740 230, 800 200 L 800 260 L 0 260 Z" fill="#14532d" fill-opacity="0.55"/>
                </svg>
            </div>
            <div class="relative z-10 flex max-w-md items-center gap-8 border-t border-white/25 pt-5 text-white/80">
                <!-- leaf / agriculture -->
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6"/></svg>
                <!-- factory / industry -->
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 5V8l-7 5V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M17 18h1M12 18h1M7 18h1"/></svg>
                <!-- buildings / enterprise -->
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="1"/><path d="M9 22v-4h6v4M9 6h.01M15 6h.01M9 10h.01M15 10h.01M9 14h.01M15 14h.01"/></svg>
                <!-- wind turbine / renewables -->
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22v-8M12 14l6-3M12 14 6 11M12 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                <!-- growth chart / impact -->
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 14 4-4 3 3 5-6"/></svg>
            </div>
        </div>

        <!-- ============================ RIGHT / SIGN-IN CARD ============================ -->
        <div class="flex flex-1 flex-col items-center justify-center px-4 py-12 sm:px-6 lg:px-14">
            <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-xl ring-1 ring-gray-100 sm:p-10">
                <!-- mobile logo -->
                <div class="mb-6 flex justify-center lg:hidden">
                    <img src="/images/maiic-logo.png" alt="MAIIC" class="h-14 w-auto"/>
                </div>

                <!-- hexagon user badge -->
                <div class="flex justify-center">
                    <div class="relative flex h-16 w-16 items-center justify-center">
                        <svg class="absolute inset-0 h-full w-full text-maiic-100" viewBox="0 0 100 100" fill="currentColor" aria-hidden="true">
                            <polygon points="50,3 93,27 93,73 50,97 7,73 7,27"/>
                        </svg>
                        <svg class="relative h-7 w-7 text-maiic-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </div>
                </div>

                <h2 class="mt-5 text-center text-3xl font-extrabold text-gray-900">Welcome back</h2>
                <p class="mt-2 text-center text-sm text-gray-500">Sign in to access your account</p>

                <jet-validation-errors class="mt-6"/>
                <div v-if="status" class="mt-6 rounded-md bg-maiic-50 px-4 py-3 text-sm font-medium text-maiic-700">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="mt-6 space-y-5">
                    <!-- email -->
                    <div class="space-y-1.5">
                        <label for="email" class="block text-sm font-semibold text-gray-700">Email address</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-maiic-600">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                            </span>
                            <input id="email" v-model="form.email" type="email" required autofocus autocomplete="username"
                                   placeholder="Enter your email"
                                   class="block w-full rounded-lg border-gray-300 py-3 pl-10 pr-3 text-gray-900 placeholder-gray-400 shadow-sm focus:border-maiic-500 focus:ring-maiic-500"/>
                        </div>
                    </div>

                    <!-- password -->
                    <div class="space-y-1.5">
                        <label for="password" class="block text-sm font-semibold text-gray-700">Password</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-maiic-600">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            </span>
                            <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" required
                                   autocomplete="current-password" placeholder="Enter your password"
                                   class="block w-full rounded-lg border-gray-300 py-3 pl-10 pr-10 text-gray-900 placeholder-gray-400 shadow-sm focus:border-maiic-500 focus:ring-maiic-500"/>
                            <button type="button" @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none"
                                    :aria-label="showPassword ? 'Hide password' : 'Show password'">
                                <svg v-if="showPassword" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61M2 2l20 20"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- remember / forgot -->
                    <div class="flex items-center justify-between">
                        <label class="flex items-center">
                            <input type="checkbox" v-model="form.remember"
                                   class="h-4 w-4 rounded border-gray-300 text-maiic-600 focus:ring-maiic-500"/>
                            <span class="ml-2 text-sm text-gray-600">Remember me</span>
                        </label>
                        <inertia-link v-if="canResetPassword" :href="route('password.request')"
                                      class="text-sm font-medium text-maiic-700 hover:text-maiic-800">
                            Forgot password?
                        </inertia-link>
                    </div>

                    <!-- CAPTCHA (self-hosted, offline) -->
                    <div class="space-y-2">
                        <label for="captcha" class="block text-sm font-semibold text-gray-700">Security check</label>
                        <div class="flex items-stretch gap-2">
                            <div class="flex flex-1 items-center justify-center overflow-hidden rounded-lg border border-gray-300 bg-gray-50">
                                <img :src="captchaSrc" alt="CAPTCHA verification code" class="h-16 w-auto max-w-full object-contain" @error="reloadCaptcha"/>
                            </div>
                            <button type="button" @click="reloadCaptcha"
                                    class="flex w-12 flex-none items-center justify-center rounded-lg border border-gray-300 bg-gray-50 text-gray-500 hover:bg-gray-100 hover:text-maiic-700 focus:outline-none focus:ring-2 focus:ring-maiic-500"
                                    aria-label="Refresh verification code" title="Get a new code">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/></svg>
                            </button>
                        </div>
                        <input id="captcha" v-model="form.captcha" type="text" required autocomplete="off"
                               inputmode="text" autocapitalize="characters" placeholder="Enter the code shown above"
                               class="block w-full rounded-lg border-gray-300 py-3 px-3 text-base uppercase tracking-[0.3em] text-gray-900 placeholder-gray-400 placeholder:normal-case placeholder:tracking-normal shadow-sm focus:border-maiic-500 focus:ring-maiic-500"/>
                        <p class="text-xs text-gray-400">Type the characters shown above. Not case-sensitive.</p>
                    </div>

                    <!-- submit -->
                    <button type="submit" :class="{ 'opacity-50 cursor-not-allowed': form.processing }" :disabled="form.processing"
                            class="flex w-full items-center justify-center gap-2 rounded-lg bg-maiic-700 py-3 px-4 text-sm font-semibold text-white shadow-sm transition-colors duration-200 hover:bg-maiic-800 focus:outline-none focus:ring-2 focus:ring-maiic-500 focus:ring-offset-2">
                        <span>{{ form.processing ? 'Signing in…' : 'Sign in' }}</span>
                        <svg v-if="!form.processing" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </button>
                </form>

                <!-- trust footer -->
                <div class="mt-6 flex items-center gap-3 text-gray-400">
                    <span class="h-px flex-1 bg-gray-200"></span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-maiic-50 text-maiic-600">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
                    </span>
                    <span class="h-px flex-1 bg-gray-200"></span>
                </div>
                <p class="mt-3 text-center text-sm text-gray-500">Secure. Reliable. Committed to Growth.</p>
            </div>
        </div>
    </div>

    <teleport to="head">
        <title>{{ pageTitle }}</title>
        <meta property="og:description" :content="pageDescription">
    </teleport>
</template>

<script>
import JetValidationErrors from '@/Jetstream/ValidationErrors.vue'

export default {
    components: {
        JetValidationErrors
    },

    props: {
        canResetPassword: Boolean,
        status: String
    },

    data() {
        return {
            form: this.$inertia.form({
                email: '',
                password: '',
                captcha: '',
                remember: false
            }),
            showPassword: false,
            captchaSrc: '/captcha?_=' + Date.now(),
            pageTitle: "Sign in — MAIIC",
            pageDescription: "Sign in to the MAIIC IFRS 9 platform.",
        }
    },

    methods: {
        reloadCaptcha() {
            this.form.captcha = ''
            this.captchaSrc = '/captcha?_=' + Date.now()
        },
        submit() {
            this.form
                .transform(data => ({
                    ...data,
                    remember: this.form.remember ? 'on' : ''
                }))
                .post(this.route('login'), {
                    onFinish: () => {
                        this.form.reset('password')
                        // the session code is consumed on each attempt — always
                        // present a fresh challenge after a submit.
                        this.reloadCaptcha()
                    },
                })
        }
    }
}
</script>
