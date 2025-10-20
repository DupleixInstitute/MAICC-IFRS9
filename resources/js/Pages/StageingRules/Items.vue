<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">SICR Items</h2>
        </template>
        <div class="mx-auto max-w-6xl">
            <div class="bg-white p-6 rounded shadow mb-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                    <div>
                        <jet-label value="Filter by Group"/>
                        <select v-model="filters.group_id" class="input" @change="applyFilter">
                            <option :value="null">All</option>
                            <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                        </select>
                    </div>
                </div>
                <form @submit.prevent="save">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <jet-label value="Group"/>
                            <select v-model.number="form.group_id" class="input">
                                <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <jet-label value="Name"/>
                            <input v-model="form.name" class="input" type="text"/>
                        </div>
                        <div class="flex items-end">
                            <jet-button :disabled="processing" :class="{ 'opacity-25': processing }">Save</jet-button>
                        </div>
                    </div>
                </form>
                <div class="mt-6 border-t pt-4">
                    <h3 class="font-medium mb-2">Bulk Import (CSV)</h3>
                    <form @submit.prevent="uploadCsv" enctype="multipart/form-data">
                        <input type="file" @change="onFile" accept=".csv,text/csv" class="mb-2"/>
                        <jet-button :disabled="processing || !csvFile" :class="{ 'opacity-25': processing }">Upload CSV</jet-button>
                    </form>
                    <p class="text-sm text-gray-500 mt-2">Headers: group, name, active</p>
                </div>
            </div>
            <div class="bg-white rounded shadow overflow-x-auto">
                <table class="w-full whitespace-no-wrap">
                    <thead class="bg-gray-50">
                        <tr class="text-left font-bold">
                            <th class="px-6 pt-4 pb-4 text-gray-500">Group</th>
                            <th class="px-6 pt-4 pb-4 text-gray-500">Name</th>
                            <th class="px-6 pt-4 pb-4 text-gray-500">Active</th>
                            <th class="px-6 pt-4 pb-4 text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="it in items.data" :key="it.id" class="hover:bg-gray-50">
                            <td class="border-t px-6 py-3">{{ it.group?.name }}</td>
                            <td class="border-t px-6 py-3">{{ it.name }}</td>
                            <td class="border-t px-6 py-3">
                                <button class="text-sm" @click="toggle(it.id)">{{ it.active ? 'Active' : 'Inactive' }}</button>
                            </td>
                            <td class="border-t px-6 py-3">
                                <button class="text-indigo-600 mr-3" @click="edit(it)">Edit</button>
                                <button class="text-red-600" @click="destroy(it.id)">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="items.data.length===0">
                            <td class="border-t px-6 py-4 text-center" colspan="4">No items</td>
                        </tr>
                    </tbody>
                </table>
                <pagination :links="items.links"/>
            </div>
        </div>
        <teleport to="head"><title>SICR Items</title></teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from '@/Jetstream/Button.vue'
import JetLabel from '@/Jetstream/Label.vue'
import Pagination from '@/Jetstream/Pagination.vue'

export default {
    props: { groups: Array, items: Object, filters: Object },
    components: { AppLayout, JetButton, JetLabel, Pagination },
    data(){
        return { form: { group_id: this.filters.group_id || (this.groups[0]?.id||null), name: '' }, processing:false, editingId:null, localFilters: { ...this.filters }, csvFile: null }
    },
    methods:{
        applyFilter(){
            this.$inertia.get(this.route('sicr-items.index'), this.filters, { preserveState: true })
        },
        save(){
            this.processing=true
            const routeName = this.editingId ? this.route('sicr-items.update', this.editingId) : this.route('sicr-items.store')
            const method = this.editingId ? 'put' : 'post'
            this.$inertia[method](routeName, this.form, { onFinish:()=>{ this.processing=false; this.editingId=null; this.form={group_id:this.groups[0]?.id||null, name:''} }})
        },
        onFile(e){ this.csvFile = e.target.files[0] },
        uploadCsv(){
            if(!this.csvFile) return
            this.processing = true
            const data = new FormData()
            data.append('file', this.csvFile)
            this.$inertia.post(this.route('sicr-items.import'), data, { onFinish:()=>{ this.processing=false; this.csvFile=null }})
        },
        edit(it){ this.editingId=it.id; this.form={ group_id: it.group_id, name: it.name }
        },
        toggle(id){ this.$inertia.post(this.route('sicr-items.toggle', id)) },
        destroy(id){ this.$inertia.delete(this.route('sicr-items.destroy', id)) }
    }
}
</script>

<style scoped>
.input{ @apply mt-1 block w-full rounded border-gray-300; }
</style>
