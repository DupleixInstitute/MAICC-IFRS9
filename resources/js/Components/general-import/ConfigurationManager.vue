<template>
  <div class="container-fluid">
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Configure Template: {{ template?.template_name }}</h3>
            <div class="card-tools">
              <router-link :to="{ name: 'general-import.templates' }" class="btn btn-default">
                <i class="fas fa-arrow-left"></i> Back to Templates
              </router-link>
            </div>
          </div>
          <div class="card-body">
            <div v-if="message" :class="['alert', `alert-${messageType}`]">
              {{ message }}
            </div>

            <!-- Add Configuration Form -->
            <form @submit.prevent="addConfiguration" class="mb-4">
              <div class="row">
                <div class="col-md-2">
                  <div class="form-group">
                    <label>Column Position <span class="text-danger">*</span></label>
                    <input 
                      type="number"
                      class="form-control"
                      :class="{ 'is-invalid': hasError('template_column_position') }"
                      v-model="form.template_column_position"
                      required>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label>Column Description <span class="text-danger">*</span></label>
                    <input 
                      type="text"
                      class="form-control"
                      :class="{ 'is-invalid': hasError('column_description') }"
                      v-model="form.column_description"
                      required>
                  </div>
                </div>
                <div class="col-md-2">
                  <div class="form-group">
                    <label>Data Type <span class="text-danger">*</span></label>
                    <select 
                      class="form-control"
                      :class="{ 'is-invalid': hasError('column_data_type') }"
                      v-model="form.column_data_type"
                      required>
                      <option v-for="(label, value) in dataTypes" :key="value" :value="value">
                        {{ label }}
                      </option>
                    </select>
                  </div>
                </div>
                <div class="col-md-2">
                  <div class="form-group">
                    <label>Minimum Value</label>
                    <input 
                      type="number"
                      class="form-control"
                      :class="{ 'is-invalid': hasError('minimum_value') }"
                      v-model="form.minimum_value"
                      step="any">
                  </div>
                </div>
                <div class="col-md-2">
                  <div class="form-group">
                    <label>Maximum Value</label>
                    <input 
                      type="number"
                      class="form-control"
                      :class="{ 'is-invalid': hasError('maximum_value') }"
                      v-model="form.maximum_value"
                      step="any">
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-md-3">
                  <div class="form-check">
                    <input 
                      type="checkbox"
                      class="form-check-input"
                      id="is_reporting_period"
                      v-model="form.is_reporting_period">
                    <label class="form-check-label" for="is_reporting_period">
                      Is Reporting Period
                    </label>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-check">
                    <input 
                      type="checkbox"
                      class="form-check-input"
                      id="is_portfolio_group_id"
                      v-model="form.is_portfolio_group_id">
                    <label class="form-check-label" for="is_portfolio_group_id">
                      Is Portfolio Group
                    </label>
                  </div>
                </div>
                <div class="col-md-6">
                  <button type="submit" class="btn btn-primary float-right" :disabled="loading">
                    <i class="fas" :class="loading ? 'fa-spinner fa-spin' : 'fa-plus'"></i>
                    {{ loading ? 'Adding...' : 'Add Configuration' }}
                  </button>
                </div>
              </div>
            </form>

            <!-- Configurations List -->
            <table class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>Position</th>
                  <th>Description</th>
                  <th>Data Type</th>
                  <th>Min Value</th>
                  <th>Max Value</th>
                  <th>Special Type</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="config in sortedConfigurations" :key="config.id">
                  <td>{{ config.template_column_position }}</td>
                  <td>{{ config.column_description }}</td>
                  <td>{{ dataTypes[config.column_data_type] }}</td>
                  <td>{{ config.minimum_value }}</td>
                  <td>{{ config.maximum_value }}</td>
                  <td>
                    <span v-if="config.is_reporting_period" class="badge badge-info mr-1">
                      Reporting Period
                    </span>
                    <span v-if="config.is_portfolio_group_id" class="badge badge-success">
                      Portfolio Group
                    </span>
                  </td>
                  <td>
                    <div class="btn-group">
                      <button 
                        @click="editConfiguration(config)"
                        class="btn btn-primary btn-sm"
                        title="Edit Configuration">
                        <i class="fas fa-edit"></i>
                      </button>
                      <button 
                        @click="deleteConfiguration(config)"
                        class="btn btn-danger btn-sm"
                        title="Delete Configuration">
                        <i class="fas fa-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!template?.configurations?.length">
                  <td colspan="7" class="text-center">No configurations found</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" ref="editModal">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <form @submit.prevent="updateConfiguration">
            <div class="modal-header">
              <h5 class="modal-title">Edit Configuration</h5>
              <button type="button" class="close" data-dismiss="modal">
                <span>&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Column Position <span class="text-danger">*</span></label>
                    <input 
                      type="number"
                      class="form-control"
                      v-model="editForm.template_column_position"
                      required>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Column Description <span class="text-danger">*</span></label>
                    <input 
                      type="text"
                      class="form-control"
                      v-model="editForm.column_description"
                      required>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label>Data Type <span class="text-danger">*</span></label>
                    <select 
                      class="form-control"
                      v-model="editForm.column_data_type"
                      required>
                      <option v-for="(label, value) in dataTypes" :key="value" :value="value">
                        {{ label }}
                      </option>
                    </select>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label>Minimum Value</label>
                    <input 
                      type="number"
                      class="form-control"
                      v-model="editForm.minimum_value"
                      step="any">
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label>Maximum Value</label>
                    <input 
                      type="number"
                      class="form-control"
                      v-model="editForm.maximum_value"
                      step="any">
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-md-6">
                  <div class="form-check">
                    <input 
                      type="checkbox"
                      class="form-check-input"
                      id="edit_is_reporting_period"
                      v-model="editForm.is_reporting_period">
                    <label class="form-check-label" for="edit_is_reporting_period">
                      Is Reporting Period
                    </label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-check">
                    <input 
                      type="checkbox"
                      class="form-check-input"
                      id="edit_is_portfolio_group_id"
                      v-model="editForm.is_portfolio_group_id">
                    <label class="form-check-label" for="edit_is_portfolio_group_id">
                      Is Portfolio Group
                    </label>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
              <button type="submit" class="btn btn-primary" :disabled="loading">
                <i class="fas" :class="loading ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                {{ loading ? 'Saving...' : 'Save Changes' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import $ from 'jquery'

export default {
  name: 'ConfigurationManager',
  
  setup() {
    const route = useRoute()
    const router = useRouter()
    const template = ref(null)
    const message = ref('')
    const messageType = ref('success')
    const loading = ref(false)
    const editModal = ref(null)
    
    const dataTypes = {
      string: 'Text',
      integer: 'Whole Number',
      decimal: 'Decimal Number',
      date: 'Date',
      datetime: 'Date and Time',
      boolean: 'Yes/No'
    }

    const form = reactive({
      template_column_position: '',
      column_description: '',
      column_data_type: '',
      minimum_value: '',
      maximum_value: '',
      is_reporting_period: false,
      is_portfolio_group_id: false
    })

    const editForm = reactive({
      id: null,
      template_column_position: '',
      column_description: '',
      column_data_type: '',
      minimum_value: '',
      maximum_value: '',
      is_reporting_period: false,
      is_portfolio_group_id: false
    })

    const sortedConfigurations = computed(() => {
      return template.value?.configurations?.sort((a, b) => 
        a.template_column_position - b.template_column_position
      ) || []
    })

    const fetchTemplate = async () => {
      try {
        const response = await axios.get(`/api/general-import/template/${route.params.id}`)
        template.value = response.data
      } catch (error) {
        message.value = 'Failed to load template'
        messageType.value = 'danger'
      }
    }

    const addConfiguration = async () => {
      loading.value = true
      try {
        await axios.post(`/api/general-import/template/${route.params.id}/configuration`, form)
        await fetchTemplate()
        resetForm()
        message.value = 'Configuration added successfully'
        messageType.value = 'success'
      } catch (error) {
        message.value = 'Failed to add configuration'
        messageType.value = 'danger'
      }
      loading.value = false
    }

    const editConfiguration = (config) => {
      Object.assign(editForm, config)
      $(editModal.value).modal('show')
    }

    const updateConfiguration = async () => {
      loading.value = true
      try {
        await axios.put(
          `/api/general-import/template/${route.params.id}/configuration/${editForm.id}`,
          editForm
        )
        await fetchTemplate()
        $(editModal.value).modal('hide')
        message.value = 'Configuration updated successfully'
        messageType.value = 'success'
      } catch (error) {
        message.value = 'Failed to update configuration'
        messageType.value = 'danger'
      }
      loading.value = false
    }

    const deleteConfiguration = async (config) => {
      if (!confirm('Are you sure you want to delete this configuration?')) return

      try {
        await axios.delete(
          `/api/general-import/template/${route.params.id}/configuration/${config.id}`
        )
        await fetchTemplate()
        message.value = 'Configuration deleted successfully'
        messageType.value = 'success'
      } catch (error) {
        message.value = 'Failed to delete configuration'
        messageType.value = 'danger'
      }
    }

    const resetForm = () => {
      Object.keys(form).forEach(key => {
        form[key] = typeof form[key] === 'boolean' ? false : ''
      })
    }

    onMounted(() => {
      fetchTemplate()
    })

    return {
      template,
      dataTypes,
      form,
      editForm,
      editModal,
      message,
      messageType,
      loading,
      sortedConfigurations,
      addConfiguration,
      editConfiguration,
      updateConfiguration,
      deleteConfiguration
    }
  }
}
</script>
