<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600"
                              :href="route('communication.campaigns.index')">
                    Campaigns
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> Edit
            </h2>
        </template>
        <div class=" mx-auto">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
                <form @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-2">
                        <div class="">
                            <jet-label for="name" value="Name"/>
                            <jet-input id="name" type="text" class="mt-1 block w-full" v-model="form.name"
                                       required autofocus/>
                            <jet-input-error :message="form.errors.name" class="mt-2" required/>
                        </div>
                        <div>
                            <jet-label for="campaign_type" value="Campaign Type"/>
                            <select
                                class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                                name="campaign_type" v-model="form.campaign_type" id="campaign_type">
                                <option value="sms">SMS</option>
                                <option value="email">Email</option>
                            </select>
                            <jet-input-error :message="form.errors.campaign_type" class="mt-2"/>
                        </div>
                        <div v-if="form.campaign_type==='sms'">
                            <jet-label for="sms_gateway_id" value="SMS Gateway"/>
                            <Multiselect
                                v-model="form.sms_gateway_id"
                                mode="single"
                                :required="true"
                                :options="$page.props.smsGateways"
                            />
                            <jet-input-error :message="form.errors.sms_gateway_id" class="mt-2"/>
                        </div>
                        <div>
                            <jet-label for="trigger_type" value="Trigger Type"/>
                            <select
                                class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                                name="trigger_type" v-model="form.trigger_type" id="trigger_type">
                                <option value="direct">Direct</option>
                                <option value="schedule">Schedule</option>
                                <option value="triggered">Triggered</option>
                            </select>
                            <jet-input-error :message="form.errors.trigger_type" class="mt-2"/>
                        </div>
                        <div v-if="form.trigger_type==='schedule'">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <jet-label for="scheduled_date" value="Schedule Date"/>
                                    <flat-pickr
                                        v-model="form.scheduled_date"
                                        class="form-control w-full"
                                        placeholder="Select date"
                                        name="date">
                                    </flat-pickr>
                                    <jet-input-error :message="form.errors.scheduled_date" class="mt-2"/>
                                </div>
                                <div>
                                    <jet-label for="scheduled_time" value="Schedule Time"/>
                                    <flat-pickr
                                        v-model="form.scheduled_time"
                                        :config="{time_24hr:true,noCalendar:true,enableTime:true,dateFormat:'H:i'}"
                                        class="form-control w-full"
                                        placeholder="Select date"
                                        name="date">
                                    </flat-pickr>
                                    <jet-input-error :message="form.errors.scheduled_time" class="mt-2"/>
                                </div>
                            </div>
                        </div>
                        <div>
                            <jet-label for="recurring">
                                <div class="flex items-center">
                                    <jet-checkbox name="recurring" id="recurring"
                                                  v-model:checked="form.recurring"/>
                                    <div class="ml-2">
                                        Recurring
                                    </div>
                                </div>
                            </jet-label>
                        </div>
                        <div v-if="form.recurring">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
                                <div>
                                    <jet-label for="recur_start_date" value="Recur Start Date"/>
                                    <flat-pickr
                                        v-model="form.recur_start_date"
                                        class="form-control w-full"
                                        placeholder="Select date"
                                        name="recur_start_date">
                                    </flat-pickr>
                                    <jet-input-error :message="form.errors.recur_start_date" class="mt-2"/>
                                </div>
                                <div>
                                    <jet-label for="recur_end_date" value="Recur End Date"/>
                                    <flat-pickr
                                        v-model="form.recur_end_date"
                                        class="form-control w-full"
                                        placeholder="Select date"
                                        name="recur_end_date">
                                    </flat-pickr>
                                    <jet-input-error :message="form.errors.recur_end_date" class="mt-2"/>
                                </div>
                                <div>
                                    <jet-label for="recur_type" value="Frequency Type"/>
                                    <select
                                        class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                                        name="recur_type"
                                        v-model="form.recur_type" id="recur_type">
                                        <option value="days">Days</option>
                                        <option value="weeks">Weeks</option>
                                        <option value="months">Months</option>
                                        <option value="years">Years</option>
                                        <option value="selected_days">Selected Days</option>
                                    </select>
                                </div>
                                <div v-if="form.recur_type!=='selected_days'">
                                    <jet-label for="recur_frequency" value="Frequency"/>
                                    <jet-input id="recur_frequency" type="text" class=" block w-full"
                                               v-model="form.recur_frequency"/>
                                    <jet-input-error :message="form.errors.recur_frequency" class="mt-2"/>
                                </div>
                                <div v-if="form.recur_type==='selected_days'">
                                    <jet-label for="selected_days" value="Frequency"/>
                                    <Multiselect
                                        v-model="form.selected_days"
                                        mode="tags"
                                        :required="true"
                                        :options="['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']"
                                    />
                                    <jet-input-error :message="form.errors.selected_days" class="mt-2"/>
                                </div>

                            </div>
                        </div>
                        <div>
                            <jet-label for="communication_campaign_business_rule_id" value="Business Rule"/>
                            <select
                                class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                                name="account_type" v-model="form.communication_campaign_business_rule_id"
                                id="communication_campaign_business_rule_id">
                                <option v-for="item in communicationCampaignBusinessRules" :value="item.id">
                                    {{ item.name }}
                                </option>
                            </select>
                            <jet-input-error :message="form.errors.communication_campaign_business_rule_id"
                                             class="mt-2"/>
                        </div>
                        <div class="mt-2" id="business_rule_msg" v-if="business_rule_description">
                            <div class=" flex items-center justify-between bg-green-500 rounded w-full">
                                <div class="p-4 text-white text-sm font-medium">{{
                                        business_rule_description
                                    }}
                                </div>
                            </div>
                        </div>
                        <div
                            v-if="form.communication_campaign_business_rule_id===43 || form.communication_campaign_business_rule_id===44 || form.communication_campaign_business_rule_id===446 "
                            class="grid grid-cols-2 gap-2">
                            <div>
                                <jet-label for="from_x" value="From"/>
                                <jet-input id="from_x" type="text" class="mt-1 block w-full"
                                           v-model="form.from_x"/>
                                <jet-input-error :message="form.errors.from_x" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="to_y" value="To"/>
                                <jet-input id="to_y" type="text" class="mt-1 block w-full"
                                           v-model="form.to_y"/>
                                <jet-input-error :message="form.errors.to_y" class="mt-2"/>
                            </div>
                        </div>
                        <div
                            v-if="form.communication_campaign_business_rule_id===3">
                            <jet-label for="course_category_id" value="Course Category"/>
                            <Multiselect
                                id="course_category_id"
                                v-model="form.course_category_id"
                                :options="courseCategories"
                            />
                            <jet-input-error :message="form.errors.course_category_id" class="mt-2"/>
                        </div>
                        <div
                            v-if="form.communication_campaign_business_rule_id===3">
                            <jet-label for="course_id" value="Course"/>
                            <Multiselect
                                v-model="form.course_id"
                                v-bind="coursesMultiSelect"
                            />
                            <jet-input-error :message="form.errors.course_id" class="mt-2"/>
                        </div>
                        <div
                            v-if="form.communication_campaign_business_rule_id===9">
                            <jet-label for="member_id" value="Member"/>
                            <Multiselect
                                v-model="form.member_id"
                                v-bind="membersMultiSelect"
                                :required="true"
                            />
                            <jet-input-error :message="form.errors.member_id" class="mt-2"/>
                        </div>
                        <div>
                            <jet-label for="branch_id" value="Branch"/>
                            <Multiselect
                                id="branch_id"
                                v-model="form.branch_id"
                                :options="branches"
                            />
                            <jet-input-error :message="form.errors.branch_id" class="mt-2"/>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-4 mt-4 gap-2"  v-if="form.communication_campaign_business_rule_id===1||form.communication_campaign_business_rule_id===2||form.communication_campaign_business_rule_id===3||form.communication_campaign_business_rule_id===4||form.communication_campaign_business_rule_id===5||form.communication_campaign_business_rule_id===6||form.communication_campaign_business_rule_id===9">
                            <div>
                                <jet-label for="province_id" value="Province"/>
                                <Multiselect
                                    id="province_id"
                                    v-model="form.province_id"
                                    :options="provinces"
                                />
                                <jet-input-error :message="form.errors.province_id" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="district_id" value="District"/>
                                <Multiselect
                                    id="district_id"
                                    v-model="form.district_id"
                                    :options="availableDistricts"
                                />
                                <jet-input-error :message="form.errors.district_id" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="ward_id" value="Ward"/>
                                <Multiselect
                                    id="district_id"
                                    v-model="form.ward_id"
                                    :options="availableWards"
                                />
                                <jet-input-error :message="form.errors.ward_id" class="mt-2"/>
                            </div> <div>
                            <jet-label for="village_id" value="Village"/>
                            <Multiselect
                                id="village_id"
                                v-model="form.village_id"
                                :options="availableVillages"
                            />
                            <jet-input-error :message="form.errors.village_id" class="mt-2"/>
                        </div>
                        </div>
                        <div
                            v-if="form.communication_campaign_business_rule_id===5">
                            <jet-label for="loan_category_id" value="Loan Category"/>
                            <Multiselect
                                id="loan_category_id"
                                v-model="form.loan_category_id"
                                :options="loanCategories"
                            />
                            <jet-input-error :message="form.errors.loan_category_id" class="mt-2"/>
                        </div>
                        <div
                            v-if="form.communication_campaign_business_rule_id===10">
                            <jet-label for="user_id" value="User"/>
                            <Multiselect
                                v-model="form.user_id"
                                v-bind="usersMultiSelect"
                                :required="true"
                            />
                            <jet-input-error :message="form.errors.user_id" class="mt-2"/>
                        </div>

                        <div>
                            <jet-label for="template_id" value="Choose Template"/>
                            <select
                                class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                                name="template_id" v-model="template_id" id="template_id">
                                <option></option>
                                <option v-for="item in selectedTemplates" :value="item.id">
                                    {{ item.name }}
                                </option>
                            </select>
                        </div>
                        <div v-if="form.campaign_type==='email'">
                            <jet-label for="subject" value="Email Subject"/>
                            <jet-input id="subject" type="text" class="mt-1 block w-full"
                                       v-model="form.subject" required/>
                            <jet-input-error :message="form.errors.subject" class="mt-2"/>
                        </div>
                        <div v-if="form.campaign_type==='email'">
                            <jet-label for="description" value="Description"/>
                            <editor
                                v-model="form.description"
                                api-key="no-api-key"
                                :init="editorConfig"
                            />
                            <jet-input-error :message="form.errors.description" class="mt-2"/>
                        </div>
                        <div v-if="form.campaign_type==='sms'">
                            <jet-label for="description" value="Description"/>
                            <textarea-input v-if="form.campaign_type==='sms'" id="description"
                                            class="mt-1 block w-full"
                                            v-model="form.description"/>
                            <jet-input-error :message="form.errors.description" class="mt-2"/>
                        </div>
                        <div v-if="form.trigger_type==='schedule' || form.trigger_type==='triggered'">
                            <jet-label for="status" value="Status"/>
                            <select
                                class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                                name="status"
                                v-model="form.status" id="status">
                                <option value="pending">Pending</option>
                                <option value="active">Active</option>
                                <option value="inactive">In-active</option>
                                <option value="done">Done</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-end mt-4">

                        <jet-button class="ml-4" :class="{ 'opacity-25': form.processing }"
                                    :disabled="form.processing">
                            Save
                        </jet-button>
                    </div>
                </form>
            </div>
        </div>
        <teleport to="head">
            <title>{{ pageTitle }}</title>
            <meta property="og:description" :content="pageDescription">
        </teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from "@/Jetstream/Button.vue";
import JetInput from "@/Jetstream/Input.vue";
import JetInputError from "@/Jetstream/InputError.vue";
import JetCheckbox from "@/Jetstream/Checkbox.vue";
import JetLabel from "@/Jetstream/Label.vue";
import Select from "@/Jetstream/Select.vue";
import FileInput from "@/Jetstream/FileInput.vue";
import TextareaInput from "@/Jetstream/TextareaInput.vue";
import Editor from "@tinymce/tinymce-vue";

const fetchMembers = async (query) => {
    let where = ''
    const response = await fetch(
        route('members.search') + '?s=' + query,
        {}
    );

    const data = await response.json(); // Here you have the data that you need
    return data.map((item) => {
        return item;
    })
}
const fetchCourses = async (query) => {
    let where = ''
    const response = await fetch(
        route('courses.search') + '?s=' + query,
        {}
    );

    const data = await response.json(); // Here you have the data that you need
    return data.map((item) => {
        return item;
    })
}
const fetchUsers = async (query) => {
    let where = ''
    const response = await fetch(
        route('users.search') + '?s=' + query,
        {}
    );

    const data = await response.json(); // Here you have the data that you need
    return data.map((item) => {
        return {value: item.id, label: item.name}
    })
}
export default {
    props: {
        communicationCampaign: Object,
        communicationCampaignBusinessRules: Object,
        communicationCampaignAttachmentTypes: Object,
        templates: Object,
        smsGateways: Object,
        loanCategories: Object,
        branches: Object,
        courseCategories: Object,
        provinces: Object,
        districts: Object,
        wards: Object,
        villages: Object,
    },
    components: {
        Editor,
        Select,
        AppLayout,
        JetButton,
        JetInput,
        JetCheckbox,
        JetLabel,
        JetInputError,
        FileInput,
        TextareaInput,

    },
    data() {
        return {
            form: this.$inertia.form({
                sms_gateway_id: this.communicationCampaign.sms_gateway_id,
                communication_campaign_business_rule_id: this.communicationCampaign.communication_campaign_business_rule_id,
                communication_campaign_attachment_type_id: this.communicationCampaign.communication_campaign_attachment_type_id,
                branch_id: this.communicationCampaign.branch_id,
                course_category_id: this.communicationCampaign.course_category_id,
                loan_category_id: this.communicationCampaign.loan_category_id,
                user_id: this.communicationCampaign.user_id,
                member_id: this.communicationCampaign.member_id,
                course_id: this.communicationCampaign.course_id,
                province_id: this.communicationCampaign.province_id,
                district_id: this.communicationCampaign.district_id,
                ward_id: this.communicationCampaign.ward_id,
                village_id: this.communicationCampaign.village_id,
                currency_id: this.communicationCampaign.currency_id,
                subject: this.communicationCampaign.subject,
                name: this.communicationCampaign.name,
                campaign_type: this.communicationCampaign.campaign_type,
                trigger_type: this.communicationCampaign.trigger_type,
                scheduled_date: this.communicationCampaign.scheduled_date,
                scheduled_time: this.communicationCampaign.scheduled_time,
                recurring: this.communicationCampaign.recurring,
                recur_frequency: this.communicationCampaign.recur_frequency,
                recur_start_date: this.communicationCampaign.recur_start_date,
                recur_end_date: this.communicationCampaign.recur_end_date,
                recur_next_date: this.communicationCampaign.recur_next_date,
                recur_type: this.communicationCampaign.recur_type,
                selected_days: this.communicationCampaign.selected_days,
                from_x: this.communicationCampaign.from_x,
                to_y: this.communicationCampaign.to_y,
                cycle_x: this.communicationCampaign.cycle_x,
                cycle_y: this.communicationCampaign.cycle_y,
                overdue_x: this.communicationCampaign.overdue_x,
                overdue_y: this.communicationCampaign.overdue_y,
                status: this.communicationCampaign.status,
                description: this.communicationCampaign.description,

            }),
            usersMultiSelect: {
                value: null,
                remark: null,
                placeholder: 'Search for User',
                filterResults: false,
                minChars: 2,
                resolveOnLoad: false,
                delay: 4,
                searchable: true,
                options: async (query) => {
                    return await fetchUsers(query)
                }
            },
            membersMultiSelect: {
                valueProp: 'id',
                label: 'name',
                selected_patient: null,
                placeholder: 'Search for Member',
                filterResults: false,
                minChars: 2,
                resolveOnLoad: true,
                delay: 4,
                searchable: true,
                options: async (query) => {
                    return await fetchMembers(query||this.form.member_id)
                }
            },
            coursesMultiSelect: {
                valueProp: 'id',
                label: 'name',
                selected_patient: null,
                placeholder: 'Search for Course',
                filterResults: false,
                minChars: 2,
                resolveOnLoad: true,
                delay: 4,
                searchable: true,
                options: async (query) => {
                    return await fetchCourses(query||this.form.course_id)
                }
            },
            business_rule_description: null,
            template_id: null,
            editorConfig: {
                menubar: 'file edit view insert format tools table help',
                plugins: 'lists link  preview image media insertdatetime help   importcss emoticons code advlist anchor autolink autoresize charmap codesample fullscreen  pagebreak quickbars save table',
                toolbar: 'styleselect | bold italic | format alignleft aligncenter alignright alignjustify | bullist numlist  table paste | anchor charmap pagebreak link image media insertdatetime code codesample emoticons save preview print fullscreen',
                images_upload_url: this.route('files.upload'),
                images_upload_base_path: '/storage',
                relative_urls: false,
                remove_script_host: false,
                convert_urls: true,
                height: "480"
            },
            pageTitle: "Edit Campaign",
            pageDescription: "Edit Campaign",
        }

    },
    methods: {
        submit() {
            this.form.put(this.route('communication.campaigns.update', this.communicationCampaign.id), {})

        },

    },
    computed: {
        selectedTemplates: function () {
            return this.templates.filter(item => {
                return item.type === this.form.campaign_type;
            })
        },
        availableDistricts: function () {
            return this.districts.filter(item => {
                return item.province_id === this.form.province_id;

            })
        },
        availableWards: function () {
            return this.wards.filter(item => {
                return item.district_id === this.form.district_id;

            })
        },
        availableVillages: function () {
            return this.villages.filter(item => {
                return item.ward_id === this.form.ward_id;

            })
        }
    },
    watch: {
        'form.communication_campaign_business_rule_id': function (val) {
            this.communicationCampaignBusinessRules.forEach(item => {
                if (val === item.id) {
                    this.business_rule_description = item.description;
                }
            })
        },
        template_id: function (val) {
            this.selectedTemplates.forEach(item => {
                if (val === item.id) {
                    this.form.description = item.description;
                    if (item.type === 'email') {
                        this.form.subject = item.subject;
                    }

                }
            })
        }
    }
}
</script>
<style scoped>

</style>
