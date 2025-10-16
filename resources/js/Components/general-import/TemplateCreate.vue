<template>
  <div class="container-fluid">
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Create Import Template</h3>
            <div class="card-tools">
              <router-link :to="{ name: 'general-import.templates' }" class="btn btn-default">
                <i class="fas fa-arrow-left"></i> Back to Templates
              </router-link>
            </div>
          </div>
          <div class="card-body">
            <div v-if="errors.length" class="alert alert-danger">
              <ul class="mb-0">
                <li v-for="(error, index) in errors" :key="index">{{ error }}</li>
              </ul>
            </div>

            <form @submit.prevent="submitForm">
              <div class="form-group">
                <label for="template_name">Template Name <span class="text-danger">*</span></label>
                <input 
                  type="text"
                  class="form-control"
                  :class="{ 'is-invalid': hasError('template_name') }"
                  id="template_name"
                  v-model="form.template_name"
                  required>
                <div v-if="hasError('template_name')" class="invalid-feedback">
                  {{ getError('template_name') }}
                </div>
              </div>

              <div class="form-group">
                <label for="template_description">Description</label>
                <textarea 
                  class="form-control"
                  :class="{ 'is-invalid': hasError('template_description') }"
                  id="template_description"
                  v-model="form.template_description"
                  rows="3">
                </textarea>
                <div v-if="hasError('template_description')" class="invalid-feedback">
                  {{ getError('template_description') }}
                </div>
              </div>

              <div class="form-group">
                <label for="source_table_name">Source Table <span class="text-danger">*</span></label>
                <select 
                  class="form-control"
                  :class="{ 'is-invalid': hasError('source_table_name') }"
                  id="source_table_name"
                  v-model="form.source_table_name"
                  required>
                  <option value="">Select a table</option>
                  <option v-for="table in tables" :key="table" :value="table">
                    {{ table }}
                  </option>
                </select>
                <div v-if="hasError('source_table_name')" class="invalid-feedback">
                  {{ getError('source_table_name') }}
                </div>
              </div>

              <div class="form-group">
                <button type="submit" class="btn btn-primary" :disabled="loading">
                  <i class="fas" :class="loading ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                  {{ loading ? 'Creating...' : 'Create Template' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

export default {
  name: 'TemplateCreate',
  
  setup() {
    const router = useRouter()
    const tables = ref([])
    const errors = ref([])
    const loading = ref(false)
    
    const form = reactive({
      template_name: '',
      template_description: '',
      source_table_name: ''
    })

    const fetchTables = async () => {
      try {
        const response = await axios.get('/api/general-import/tables')
        tables.value = response.data
      } catch (error) {
        errors.value = ['Failed to load tables']
      }
    }

    const submitForm = async () => {
      loading.value = true
      errors.value = []

      try {
        await axios.post('/api/general-import/template', form)
        router.push({ 
          name: 'general-import.templates',
          query: { message: 'Template created successfully' }
        })
      } catch (error) {
        if (error.response?.data?.errors) {
          errors.value = Object.values(error.response.data.errors).flat()
        } else {
          errors.value = ['Failed to create template']
        }
        loading.value = false
      }
    }

    const hasError = (field) => {
      return errors.value.some(error => error.toLowerCase().includes(field.toLowerCase()))
    }

    const getError = (field) => {
      return errors.value.find(error => error.toLowerCase().includes(field.toLowerCase()))
    }

    onMounted(fetchTables)

    return {
      form,
      tables,
      errors,
      loading,
      submitForm,
      hasError,
      getError
    }
  }
}
</script>
