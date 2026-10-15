p='resources/js/Pages/Collateral/Components/Allocate.vue'
s=open(p,encoding='utf-8').read()
old="""      form.post('/collateral/allocate/auto', {
        preserveScroll: true,
        onSuccess: () => {
          alert(' Collateral allocated successfully.')
          resetForm()
        },
        onError: () => {
          alert(' There was an error allocating collateral.')
        },
      })"""
assert old in s
s=s.replace(old,"""      // the server redirects to the allocations with a success message;
      // validation errors show under each field
      form.post(route('collateral.allocate.auto'), { preserveScroll: true })""")
old="""import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from '@/Jetstream/Button.vue'
import JetLabel from '@/Jetstream/Label.vue'
"""
assert old in s
s=s.replace(old,"""import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
""")
old="""  props: {
    registerDates: Array, // from backend
  },
  components: {
    AppLayout,
    JetButton,
    JetLabel,
  },"""
assert old in s
s=s.replace(old,"""  props: {
    registerDates: { type: Array, default: () => [] },
  },
  components: {
    AppLayout,
    Link,
  },""")
open(p,'w',encoding='utf-8').write(s)
