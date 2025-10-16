<template>
  <div class="container-fluid">
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Import Data: {{ template?.template_name }}</h3>
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

            <div class="row mb-4">
              <div class="col-md-12">
                <div class="alert alert-info">
                  <h5><i class="icon fas fa-info"></i> Template Information</h5>
                  <p>{{ template?.template_description }}</p>
                  <p><strong>Source Table:</strong> {{ template?.source_table_name }}</p>
                  <p><strong>Required Columns:</strong> {{ template?.column_count }}</p>
                </div>
              </div>
            </div>

            <div class="row mb-4">
              <div class="col-md-12">
                <a :href="sampleTemplateUrl" class="btn btn-success">
                  <i class="fas fa-download"></i> Download Sample Template
                </a>
              </div>
            </div>

            <form @submit.prevent="processImport">
              <div v-if="hasReportingPeriod" class="form-group">
                <label for="reporting_period">Reporting Period <span class="text-danger">*</span></label>
                <select 
                  class="form-control"
                  :class="{ 'is-invalid': hasError('reporting_period') }"
                  id="reporting_period"
                  v-model="form.reporting_period"
                  required>
                  <option value="">Select Reporting Period</option>
                  <option v-for="period in reportingPeriods" :key="period.id" :value="period.id">
                    {{ period.name }}
                  </option>
                </select>
                <div v-if="hasError('reporting_period')" class="invalid-feedback">
                  {{ getError('reporting_period') }}
                </div>
              </div>

              <div v-if="hasPortfolioGroup" class="form-group">
                <label for="portfolio_group">Portfolio Group <span class="text-danger">*</span></label>
                <select 
                  class="form-control"
                  :class="{ 'is-invalid': hasError('portfolio_group') }"
                  id="portfolio_group"
                  v-model="form.portfolio_group"
                  required>
                  <option value="">Select Portfolio Group</option>
                  <option v-for="group in portfolioGroups" :key="group.id" :value="group.id">
                    {{ group.name }}
                  </option>
                </select>
                <div v-if="hasError('portfolio_group')" class="invalid-feedback">
                  {{ getError('portfolio_group') }}
                </div>
              </div>

              <div class="form-group">
                <label for="import_file">Import File <span class="text-danger">*</span></label>
                <div class="custom-file">
                  <input 
                    type="file"
                    class="custom-file-input"
                    :class="{ 'is-invalid': hasError('import_file') }"
                    id="import_file"
                    ref="fileInput"
                    @change="handleFileChange"
                    accept=".csv,text/csv"
                    required>
                  <label class="custom-file-label" for="import_file">
                    {{ selectedFileName || 'Choose file' }}
                  </label>
                  <div v-if="hasError('import_file')" class="invalid-feedback">
                    {{ getError('import_file') }}
                  </div>
                </div>
                <small class="form-text text-muted">
                  Please upload a CSV file following the template format.
                </small>
              </div>

              <div class="form-group">
                <button type="submit" class="btn btn-primary" :disabled="loading">
                  <i class="fas" :class="loading ? 'fa-spinner fa-spin' : 'fa-upload'"></i>
                  {{ loading ? 'Processing...' : 'Process Import' }}
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
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import bsCustomFileInput from 'bs-custom-file-input'

export default {
  name: 'ImportForm',
  
  setup() {
    const route = useRoute()
    const router = useRouter()
    const fileInput = ref(null)
    const template = ref(null)
    const reportingPeriods = ref([])
    const portfolioGroups = ref([])
    const message = ref('')
    const messageType = ref('success')
    const loading = ref(false)
    const selectedFileName = ref('')
    const errors = ref([])

    const form = reactive({
      reporting_period: '',
      portfolio_group: '',
      import_file: null
    })

    const hasReportingPeriod = computed(() => {
      return template.value?.configurations?.some(c => c.is_reporting_period)
    })

    const hasPortfolioGroup = computed(() => {
      return template.value?.configurations?.some(c => c.is_portfolio_group_id)
    })

    const sampleTemplateUrl = computed(() => {
      return `/api/general-import/template/${route.params.id}/sample`
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

    const fetchReportingPeriods = async () => {
      try {
        const response = await axios.get('/api/general-import/reporting-periods')
        reportingPeriods.value = response.data
      } catch (error) {
        message.value = 'Failed to load reporting periods'
        messageType.value = 'danger'
      }
    }

    const fetchPortfolioGroups = async () => {
      try {
        const response = await axios.get('/api/general-import/portfolio-groups')
        portfolioGroups.value = response.data
      } catch (error) {
        message.value = 'Failed to load portfolio groups'
        messageType.value = 'danger'
      }
    }

    const handleFileChange = (event) => {
      const file = event.target.files[0]
      if (file) {
        selectedFileName.value = file.name
        form.import_file = file
      }
    }

    const processImport = async () => {
      loading.value = true
      errors.value = []

      const formData = new FormData()
      formData.append('import_file', form.import_file)
      if (hasReportingPeriod.value) {
        formData.append('reporting_period', form.reporting_period)
      }
      if (hasPortfolioGroup.value) {
        formData.append('portfolio_group', form.portfolio_group)
      }

      try {
        await axios.post(`/api/general-import/template/${route.params.id}/import`, formData, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        })
        message.value = 'Import completed successfully'
        messageType.value = 'success'
        resetForm()
      } catch (error) {
        if (error.response?.data?.errors) {
          errors.value = Object.values(error.response.data.errors).flat()
        } else {
          message.value = 'Import failed'
          messageType.value = 'danger'
        }
      }
      loading.value = false
    }

    const resetForm = () => {
      form.reporting_period = ''
      form.portfolio_group = ''
      form.import_file = null
      selectedFileName.value = ''
      if (fileInput.value) {
        fileInput.value.value = ''
      }
    }

    const hasError = (field) => {
      return errors.value.some(error => error.toLowerCase().includes(field.toLowerCase()))
    }

    const getError = (field) => {
      return errors.value.find(error => error.toLowerCase().includes(field.toLowerCase()))
    }

    onMounted(() => {
      bsCustomFileInput.init()
      fetchTemplate()
      if (hasReportingPeriod.value) {
        fetchReportingPeriods()
      }
      if (hasPortfolioGroup.value) {
        fetchPortfolioGroups()
      }
    })

    return {
      template,
      reportingPeriods,
      portfolioGroups,
      form,
      fileInput,
      message,
      messageType,
      loading,
      selectedFileName,
      errors,
      hasReportingPeriod,
      hasPortfolioGroup,
      sampleTemplateUrl,
      handleFileChange,
      processImport,
      hasError,
      getError
    }
  }
}
</script>
