<template>
    <!-- The user form, shared by Add user and Edit user. Fields and names are
         the ones UsersController validates; nothing here changes the rules. -->
    <div>
        <div class="maiic-section-title mt-0">Person</div>
        <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
            <div>
                <label for="name" class="maiic-flabel">Full name</label>
                <input id="name" v-model="form.name" type="text" class="maiic-input" required autofocus autocomplete="name">
                <p v-if="form.errors.name" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.name }}</p>
            </div>
            <div>
                <label for="email" class="maiic-flabel">Email (used to sign in)</label>
                <input id="email" v-model="form.email" type="email" class="maiic-input" required>
                <p v-if="form.errors.email" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.email }}</p>
            </div>
            <div>
                <label for="gender" class="maiic-flabel">Gender</label>
                <select id="gender" v-model="form.gender" class="maiic-select">
                    <option :value="null" disabled>Select</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
                <p v-if="form.errors.gender" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.gender }}</p>
            </div>
            <div>
                <label for="mobile" class="maiic-flabel">Mobile</label>
                <input id="mobile" v-model="form.mobile" type="text" class="maiic-input">
                <p v-if="form.errors.mobile" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.mobile }}</p>
            </div>
            <div>
                <label for="tel" class="maiic-flabel">Telephone</label>
                <input id="tel" v-model="form.tel" type="text" class="maiic-input">
                <p v-if="form.errors.tel" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.tel }}</p>
            </div>
            <div>
                <label for="photo" class="maiic-flabel">Photo (optional, up to 1 MB)</label>
                <input id="photo" type="file" accept="image/*" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-maiic-50 file:px-3 file:py-2 file:text-sm file:font-bold file:text-maiic-700 hover:file:bg-maiic-100" @change="form.photo = $event.target.files[0]">
                <p v-if="form.errors.photo" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.photo }}</p>
            </div>
        </div>

        <div class="maiic-section-title">Access</div>
        <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
            <div class="md:col-span-2">
                <label for="roles" class="maiic-flabel">Roles</label>
                <Multiselect id="roles" v-model="form.roles" mode="tags" :options="roles" placeholder="Choose one or more roles"/>
                <p v-if="form.errors.roles" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.roles }}</p>
            </div>
            <div>
                <label for="can_reassign" class="maiic-flabel">Can hand work to other users</label>
                <select id="can_reassign" v-model="form.can_reassign" class="maiic-select">
                    <option :value="0">No</option>
                    <option :value="1">Yes</option>
                </select>
                <p v-if="form.errors.can_reassign" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.can_reassign }}</p>
            </div>
            <div>
                <label for="password" class="maiic-flabel">{{ editing ? 'New password (leave blank to keep)' : 'Password' }}</label>
                <input id="password" v-model="form.password" type="password" class="maiic-input" :required="!editing" autocomplete="new-password">
                <p v-if="form.errors.password" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.password }}</p>
            </div>
            <div>
                <label for="password_confirmation" class="maiic-flabel">Confirm password</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="maiic-input" :required="!editing" autocomplete="new-password">
                <p v-if="form.errors.password_confirmation" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.password_confirmation }}</p>
            </div>
            <div class="flex flex-col justify-end gap-2 pb-1">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="form.active" type="checkbox" class="rounded border-gray-300 text-maiic-600 focus:ring-maiic-500"> Active (can sign in)
                </label>
                <label v-if="!editing" class="flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="form.send_login_details" type="checkbox" class="rounded border-gray-300 text-maiic-600 focus:ring-maiic-500"> Email the sign-in details
                </label>
            </div>
        </div>

        <div class="maiic-section-title">Other</div>
        <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
            <div>
                <label for="branch_id" class="maiic-flabel">Branch</label>
                <Multiselect id="branch_id" v-model="form.branch_id" mode="single" :searchable="true" :options="branches" placeholder="Choose a branch"/>
                <p v-if="form.errors.branch_id" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.branch_id }}</p>
            </div>
            <div>
                <label for="group_email" class="maiic-flabel">Group email</label>
                <input id="group_email" v-model="form.group_email" type="email" class="maiic-input">
                <p v-if="form.errors.group_email" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.group_email }}</p>
            </div>
            <div>
                <label for="external_id" class="maiic-flabel">External ID</label>
                <input id="external_id" v-model="form.external_id" type="text" class="maiic-input">
                <p v-if="form.errors.external_id" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.external_id }}</p>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        form: { type: Object, required: true },
        roles: { type: [Array, Object], default: () => [] },
        branches: { type: [Array, Object], default: () => [] },
        editing: { type: Boolean, default: false },
    },
}
</script>
