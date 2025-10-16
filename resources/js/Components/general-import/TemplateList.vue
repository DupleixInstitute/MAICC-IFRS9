<template>
  <div class="container-fluid">
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Import Templates</h3>
            <div class="card-tools">
              <router-link :to="{ name: 'general-import.template.create' }" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create New Template
              </router-link>
            </div>
          </div>
          <div class="card-body">
            <div v-if="message" :class="['alert', `alert-${messageType}`]">
              {{ message }}
            </div>

            <table class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>Template Name</th>
                  <th>Description</th>
                  <th>Source Table</th>
                  <th>Columns</th>
                  <th>Import Count</th>
                  <th>Status</th>
                  <th>Last Updated</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="template in templates" :key="template.id">
                  <td>{{ template.template_name }}</td>
                  <td>{{ template.template_description }}</td>
                  <td>{{ template.source_table_name }}</td>
                  <td>{{ template.column_count }}</td>
                  <td>{{ template.import_count }}</td>
                  <td>
                    <span :class="['badge', template.active_status === 1 ? 'badge-success' : 'badge-danger']">
                      {{ template.active_status === 1 ? 'Active' : 'Inactive' }}
                    </span>
                  </td>
                  <td>{{ formatDate(template.update_date) }}</td>
                  <td>
                    <div class="btn-group">
                      <router-link 
                        :to="{ name: 'general-import.configuration.index', params: { id: template.id }}"
                        class="btn btn-info btn-sm"
                        title="Configure Columns">
                        <i class="fas fa-cogs"></i>
                      </router-link>
                      <router-link 
                        :to="{ name: 'general-import.template.edit', params: { id: template.id }}"
                        class="btn btn-primary btn-sm"
                        title="Edit Template">
                        <i class="fas fa-edit"></i>
                      </router-link>
                      <router-link 
                        v-if="template.active_status === 1"
                        :to="{ name: 'general-import.form', params: { id: template.id }}"
                        class="btn btn-success btn-sm"
                        title="Import Data">
                        <i class="fas fa-upload"></i>
                      </router-link>
                      <button 
                        @click="toggleStatus(template)"
                        :class="['btn', 'btn-sm', template.active_status === 1 ? 'btn-warning' : 'btn-success']"
                        :title="template.active_status === 1 ? 'Deactivate' : 'Activate'">
                        <i :class="['fas', template.active_status === 1 ? 'fa-ban' : 'fa-check']"></i>
                      </button>
                      <button 
                        @click="deleteTemplate(template)"
                        class="btn btn-danger btn-sm"
                        title="Delete Template">
                        <i class="fas fa-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="templates.length === 0">
                  <td colspan="8" class="text-center">No templates found</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { format } from 'date-fns'

export default {
  name: 'TemplateList',
  
  setup() {
    const templates = ref([])
    const message = ref('')
    const messageType = ref('success')

    const fetchTemplates = async () => {
      try {
        const response = await axios.get('/api/general-import/templates')
        templates.value = response.data
      } catch (error) {
        message.value = 'Failed to load templates'
        messageType.value = 'danger'
      }
    }

    const toggleStatus = async (template) => {
      try {
        await axios.put(`/api/general-import/template/${template.id}/toggle-status`)
        await fetchTemplates()
        message.value = `Template ${template.active_status === 1 ? 'deactivated' : 'activated'} successfully`
        messageType.value = 'success'
      } catch (error) {
        message.value = 'Failed to update template status'
        messageType.value = 'danger'
      }
    }

    const deleteTemplate = async (template) => {
      if (!confirm('Are you sure you want to delete this template?')) return

      try {
        await axios.delete(`/api/general-import/template/${template.id}`)
        await fetchTemplates()
        message.value = 'Template deleted successfully'
        messageType.value = 'success'
      } catch (error) {
        message.value = 'Failed to delete template'
        messageType.value = 'danger'
      }
    }

    const formatDate = (date) => {
      return date ? format(new Date(date), 'yyyy-MM-dd HH:mm') : 'Never'
    }

    onMounted(fetchTemplates)

    return {
      templates,
      message,
      messageType,
      toggleStatus,
      deleteTemplate,
      formatDate
    }
  }
}
</script>
