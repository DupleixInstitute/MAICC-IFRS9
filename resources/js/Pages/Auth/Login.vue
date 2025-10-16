<template>
    <div class="flex min-h-screen overflow-hidden">
        <div class="relative hidden w-0 flex-1 lg:block">
            <img class="absolute inset-0 h-full w-full object-cover"
                 src="../../Assets/land-bg.png"
                 alt="">
        </div>
        <div class="flex flex-1 flex-col justify-center py-12 px-4 sm:px-6 lg:flex-none lg:px-20 xl:px-24 bg-green-600">
            <div class="mx-auto w-full max-w-sm lg:w-96">
                <div>
                    <div class="w-full sm:max-w-md mt-6 px-6 py-4 overflow-hidden">
                        <jet-authentication-card-logo/>
                    </div>
                    <h2 class="mt-6 text-3xl font-extrabold text-gray-900">Welcome back</h2>
                    <p class="mt-2 text-sm text-white-50">
                        Sign in to access your account
                    </p>
                </div>

                <div class="mt-8">
                    <div class="mt-6">
                        <jet-validation-errors class="mb-4"/>
                        <div v-if="status" class="mb-4 font-medium text-sm text-green-600">
                            {{ status }}
                        </div>
                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="space-y-2">
                                <jet-label for="email" value="Email address" class="text-sm font-medium text-gray-50"/>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-900" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                        </svg>
                                    </div>
                                    <jet-input id="email" type="email" class="mt-1 block w-full pl-10 pr-3" v-model="form.email"
                                           required autofocus/>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <jet-label for="password" value="Password" class="text-sm font-medium text-gray-50"/>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-900" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <jet-input id="password" :type="showPassword ? 'text' : 'password'" class="mt-1 block w-full pl-10 pr-10"
                                           v-model="form.password" required
                                           autocomplete="current-password"/>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                        <button type="button" @click="showPassword = !showPassword" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                                            <svg v-if="showPassword" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                            </svg>
                                            <svg v-else class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd" />
                                                <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <label class="flex items-center">
                                    <jet-checkbox name="remember" v-model:checked="form.remember"/>
                                    <span class="ml-2 text-sm text-gray-50">Remember me</span>
                                </label>
                                <inertia-link v-if="canResetPassword" :href="route('password.request')"
                                              class="text-sm font-medium text-gray-80 hover:text-gray-100 transition-colors duration-200">
                                    Forgot password?
                                </inertia-link>
                            </div>

                            <div>
                                <button type="submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing"
                                        class="flex w-full justify-center rounded-md border border-transparent bg-yellow-400 py-3 px-4 text-sm font-semibold text-gray-900 shadow-sm hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-yellow-300 focus:ring-offset-2 transition-colors duration-200">
                                    Sign in
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <teleport to="head">
        <title>{{ pageTitle }}</title>
        <meta property="og:description" :content="pageDescription">
    </teleport>
</template>

<script>
import JetAuthenticationCardLogo from '@/Jetstream/AuthenticationCardLogo.vue'
import JetButton from '@/Jetstream/Button.vue'
import JetInput from '@/Jetstream/Input.vue'
import JetCheckbox from '@/Jetstream/Checkbox.vue'
import JetLabel from '@/Jetstream/Label.vue'
import JetValidationErrors from '@/Jetstream/ValidationErrors.vue'

export default {
    components: {
        JetAuthenticationCardLogo,
        JetButton,
        JetInput,
        JetCheckbox,
        JetLabel,
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
                remember: false
            }),
            showPassword: false,
            pageTitle: "Login",
            pageDescription: "Login",
        }
    },

    methods: {
        submit() {
            this.form
                .transform(data => ({
                    ...data,
                    remember: this.form.remember ? 'on' : ''
                }))
                .post(this.route('login'), {
                    //log some data in console
                    onFinish: () => this.form.reset('password'),
                })
        }
    }
}
</script>
