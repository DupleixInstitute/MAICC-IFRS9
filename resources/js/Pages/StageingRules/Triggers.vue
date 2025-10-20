<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">SICR Trigger Alerts</h2>
        </template>
        <div class="mx-auto max-w-5xl">
            <div class="bg-white p-6 rounded shadow mb-6">
                <form @submit.prevent="submit">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <jet-label value="Group"/>
                            <select v-model.number="form.group_id" class="input" @change="filterItems">
                                <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                            </select>
                        </div>
                        <div>
                            <jet-label value="Item"/>
                            <select v-model.number="form.item_id" class="input">
                                <option v-for="i in filteredItems" :key="i.id" :value="i.id">{{ i.name }}</option>
                            </select>
                        </div>
                        <div>
                            <jet-label value="Account Number"/>
                            <input v-model="form.account_number" class="input" type="text"/>
                        </div>
                        <div>
                            <jet-label value="Attachment (optional)"/>
                            <input @change="onFile" class="input" type="file"/>
                        </div>
                        <div class="md:col-span-2">
                            <jet-label value="Reason"/>
                            <textarea v-model="form.reason" class="input" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="mt-4">
                        <jet-button :disabled="processing" :class="{ 'opacity-25': processing }">Trigger Alert</jet-button>
                    </div>
                </form>
            </div>
            <div class="bg-white rounded shadow overflow-x-auto">
                <table class="w-full whitespace-no-wrap">
                    <thead class="bg-gray-50">
                        <tr class="text-left font-bold">
                            <th class="px-6 pt-4 pb-4 text-gray-500">When</th>
                            <th class="px-6 pt-4 pb-4 text-gray-500">Group</th>
                            <th class="px-6 pt-4 pb-4 text-gray-500">Item</th>
                            <th class="px-6 pt-4 pb-4 text-gray-500">Account</th>
                            <th class="px-6 pt-4 pb-4 text-gray-500">By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in triggers.data" :key="t.id" class="hover:bg-gray-50">
                            <td class="border-t px-6 py-3">{{ new Date(t.created_at).toLocaleString() }}</td>
                            <td class="border-t px-6 py-3">{{ t.group?.name }}</td>
                            <td class="border-t px-6 py-3">{{ t.item?.name }}</td>
                            <td class="border-t px-6 py-3">{{ t.account_number }}</td>
                            <td class="border-t px-6 py-3">{{ t.user?.name }}</td>
                        </tr>
                        <tr v-if="triggers.data.length===0">
                            <td class="border-t px-6 py-4 text-center" colspan="5">No triggers</td>
                        </tr>
                    </tbody>
                </table>
                <pagination :links="triggers.links"/>
            </div>
        </div>
        <teleport to="head"><title>SICR Triggers</title></teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from '@/Jetstream/Button.vue'
import JetLabel from '@/Jetstream/Label.vue'
import Pagination from '@/Jetstream/Pagination.vue'

export default {
    props: { groups: Array, items: Array, triggers: Object },
    components: { AppLayout, JetButton, JetLabel, Pagination },
    data(){
        return { form: { group_id: this.groups[0]?.id||null, item_id: null, account_number: '', reason: '', attachment: null }, processing:false }
    },
    computed:{
        filteredItems(){ return this.items.filter(i => i.group_id === this.form.group_id) }
    },
    methods:{
        filterItems(){ if(!this.filteredItems.find(i => i.id === this.form.item_id)) this.form.item_id = this.filteredItems[0]?.id || null },
        onFile(e){ this.form.attachment = e.target.files[0] },
        submit(){
            this.processing=true
            const data = new FormData()
            Object.keys(this.form).forEach(k=>{ if(this.form[k]!==null) data.append(k, this.form[k]) })
            this.$inertia.post(this.route('sicr-triggers.store'), data, { onFinish:()=>{ this.processing=false }})
        }
    }
}
</script>

<style scoped>
.input{ @apply mt-1 block w-full rounded border-gray-300; }
</style>
