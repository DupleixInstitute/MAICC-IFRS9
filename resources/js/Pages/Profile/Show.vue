<template>
    <app-layout title="My profile" description="Your name, email and photo, your password, two-step sign-in and where you are signed in">
        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 pt-4">
                <nav class="flex gap-6 overflow-x-auto">
                    <button v-for="t in tabs" :key="t.key" type="button" class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-semibold"
                            :class="tab === t.key ? 'border-maiic-600 text-maiic-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
                            @click="tab = t.key">
                        {{ t.label }}
                        <span v-if="t.count !== undefined" class="ml-1 rounded-full px-2 py-0.5 text-xs" :class="tab === t.key ? 'bg-maiic-100 text-maiic-800' : 'bg-gray-100 text-gray-600'">{{ t.count }}</span>
                    </button>
                </nav>
            </div>
            <div class="p-6">
                <update-profile-information-form v-if="tab === 'profile'" :user="$page.props.user"/>
                <update-password-form v-else-if="tab === 'password'"/>
                <two-factor-authentication-form v-else-if="tab === 'twofactor'"/>
                <logout-other-browser-sessions-form v-else-if="tab === 'sessions'" :sessions="sessions"/>
                <delete-user-form v-else-if="tab === 'delete'"/>
            </div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import DeleteUserForm from './DeleteUserForm.vue'
import LogoutOtherBrowserSessionsForm from './LogoutOtherBrowserSessionsForm.vue'
import TwoFactorAuthenticationForm from './TwoFactorAuthenticationForm.vue'
import UpdatePasswordForm from './UpdatePasswordForm.vue'
import UpdateProfileInformationForm from './UpdateProfileInformationForm.vue'

export default {
    props: ['sessions'],
    components: {
        AppLayout,
        DeleteUserForm,
        LogoutOtherBrowserSessionsForm,
        TwoFactorAuthenticationForm,
        UpdatePasswordForm,
        UpdateProfileInformationForm,
    },
    data() {
        return { tab: 'profile' }
    },
    computed: {
        // Only the features this installation switches on get a tab.
        tabs() {
            const j = this.$page.props.jetstream || {}
            return [
                j.canUpdateProfileInformation && { key: 'profile', label: 'Profile details' },
                j.canUpdatePassword && { key: 'password', label: 'Password' },
                j.canManageTwoFactorAuthentication && { key: 'twofactor', label: 'Two-step sign-in' },
                { key: 'sessions', label: 'Signed-in browsers', count: (this.sessions || []).length },
                j.hasAccountDeletionFeatures && { key: 'delete', label: 'Delete account' },
            ].filter(Boolean)
        },
    },
    created() {
        if (!this.tabs.some(t => t.key === this.tab)) this.tab = this.tabs[0]?.key
    },
}
</script>
