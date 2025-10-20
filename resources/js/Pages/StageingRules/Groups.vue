<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">SICR Groups</h2>
        </template>
        <div class="mx-auto max-w-5xl">
            <div class="bg-white p-6 rounded shadow mb-4">
                <form @submit.prevent="save">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <jet-label value="Name"/>
                            <input v-model="form.name" class="input" type="text"/>
                        </div>
                        <div class="md:col-span-2">
                            <jet-label value="Description"/>
                            <input v-model="form.description" class="input" type="text"/>
                        </div>
                    </div>
                    <div class="mt-4">
                        <jet-button :disabled="processing" :class="{ 'opacity-25': processing }">Save</jet-button>
                    </div>
                </form>
                <div class="mt-6 border-t pt-4">
                    <h3 class="font-medium mb-2">Bulk Import (CSV)</h3>
                    <form @submit.prevent="uploadCsv" enctype="multipart/form-data">
                        <input type="file" @change="onFile" accept=".csv,text/csv" class="mb-2"/>
                        <jet-button :disabled="processing || !csvFile" :class="{ 'opacity-25': processing }">Upload CSV</jet-button>
                    </form>
                    <p class="text-sm text-gray-500 mt-2">Headers: name, description</p>
                </div>
            </div>
            <div class="bg-white rounded shadow overflow-x-auto">
                <table class="w-full whitespace-no-wrap">
                    <thead class="bg-gray-50">
                        <tr class="text-left font-bold">
                            <th class="px-6 pt-4 pb-4 text-gray-500">Name</th>
                            <th class="px-6 pt-4 pb-4 text-gray-500">Description</th>
                            <th class="px-6 pt-4 pb-4 text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="g in groups.data" :key="g.id" class="hover:bg-gray-50">
                            <td class="border-t px-6 py-3">{{ g.name }}</td>
                            <td class="border-t px-6 py-3">{{ g.description }}</td>
                            <td class="border-t px-6 py-3">
                                <button class="text-indigo-600 mr-3" @click="edit(g)">Edit</button>
                                <button class="text-red-600" @click="destroy(g.id)">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="groups.data.length===0">
                            <td class="border-t px-6 py-4 text-center" colspan="3">No groups</td>
                        </tr>
                    </tbody>
                </table>
                <pagination :links="groups.links"/>
            </div>
        </div>
        <teleport to="head"><title>SICR Groups</title></teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from '@/Jetstream/Button.vue'
import JetLabel from '@/Jetstream/Label.vue'
import Pagination from '@/Jetstream/Pagination.vue'

export default {
    props: { groups: Object },
    components: { AppLayout, JetButton, JetLabel, Pagination },
    data(){
        return { form: { name: '', description: '' }, processing:false, editingId:null, csvFile: null }
    },
    methods:{
        save(){
            this.processing=true
            const routeName = this.editingId ? this.route('sicr-groups.update', this.editingId) : this.route('sicr-groups.store')
            const method = this.editingId ? 'put' : 'post'
            this.$inertia[method](routeName, this.form, { onFinish:()=>{ this.processing=false; this.editingId=null; this.form={name:'',description:''} }})
        },
        onFile(e){ this.csvFile = e.target.files[0] },
        uploadCsv(){
            if(!this.csvFile) return
            this.processing = true
            const data = new FormData()
            data.append('file', this.csvFile)
            this.$inertia.post(this.route('sicr-groups.import'), data, { onFinish:()=>{ this.processing=false; this.csvFile=null }})
        },
        edit(g){ this.editingId=g.id; this.form={ name:g.name, description:g.description }
        },
        destroy(id){ this.$inertia.delete(this.route('sicr-groups.destroy', id)) }
    }
}
</script>

<style scoped>
.input{ @apply mt-1 block w-full rounded border-gray-300; }
</style>
