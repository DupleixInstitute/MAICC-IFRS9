import TemplateList from '../components/general-import/TemplateList.vue'
import TemplateCreate from '../components/general-import/TemplateCreate.vue'
import ConfigurationManager from '../components/general-import/ConfigurationManager.vue'
import ImportForm from '../components/general-import/ImportForm.vue'

export default [
    {
        path: '/general-import/templates',
        name: 'general-import.templates',
        component: TemplateList,
        meta: {
            title: 'Import Templates',
            requiresAuth: true
        }
    },
    {
        path: '/general-import/template/create',
        name: 'general-import.template.create',
        component: TemplateCreate,
        meta: {
            title: 'Create Import Template',
            requiresAuth: true
        }
    },
    {
        path: '/general-import/template/:id/edit',
        name: 'general-import.template.edit',
        component: TemplateCreate,
        props: true,
        meta: {
            title: 'Edit Import Template',
            requiresAuth: true
        }
    },
    {
        path: '/general-import/template/:id/configuration',
        name: 'general-import.configuration.index',
        component: ConfigurationManager,
        props: true,
        meta: {
            title: 'Configure Import Template',
            requiresAuth: true
        }
    },
    {
        path: '/general-import/template/:id/import',
        name: 'general-import.form',
        component: ImportForm,
        props: true,
        meta: {
            title: 'Import Data',
            requiresAuth: true
        }
    }
]
