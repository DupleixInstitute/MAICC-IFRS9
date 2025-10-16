<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Comments for Loan Application ID: {{ loanApplicationId }}
            </h2>
        </template>

        <!-- Add Comment Button -->
        <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8 mb-4 flex justify-between">
            <button class="btn-primary px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                    @click="showAddCommentModal = true">
             Add New Comment +
            </button>
            <inertia-link class="btn-primary px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
            :href="route('loan_applications.show', loanApplicationId)">
            Return to Loan Profile 🔙
            </inertia-link>
        </div>

        <!-- Comments List -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="divide-y divide-gray-200">
                    <div v-if="!comments.data.length" class="p-6 text-center text-gray-500">
                        No comments yet. Be the first to add one.
                    </div>

                    <template v-else>
                        <div v-for="comment in comments.data"
                             :key="comment.id"
                             class="p-6 hover:bg-gray-50">
                            <!-- Main Comment -->
                            <div class="flex space-x-3">
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-sm font-medium text-gray-900">
                                            {{ comment.user.name }}
                                            <span class="text-gray-500 text-xs ml-2">
                                                {{ formatDate(comment.created_at) }}
                                            </span>
                                        </h3>
                                        <span class="text-sm text-gray-500">
                                            {{ comment.comment_section.name }}
                                        </span>
                                    </div>
                                    <div class="mt-2 text-sm text-gray-700">
                                        {{ comment.content }}
                                    </div>

                                    <!-- Attachments -->
                                    <div v-if="comment.attachments?.length" class="mt-2">
                                        <div v-for="attachment in comment.attachments"
                                             :key="attachment.id"
                                             class="flex items-center space-x-2">
                                            <svg class="w-4 h-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M8 4a3 3 0 00-3 3v4a5 5 0 0010 0V7a1 1 0 112 0v4a7 7 0 11-14 0V7a5 5 0 0110 0v4a3 3 0 11-6 0V7a1 1 0 012 0v4a1 1 0 102 0V7a3 3 0 00-3-3z" clip-rule="evenodd" />
                                            </svg>
                                            <a :href="getDownloadUrl(attachment.file_path)"
                                               class="text-sm text-blue-600 hover:text-blue-800">
                                                {{ attachment.file_name }}
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="mt-2 flex space-x-2">
                                        <button @click="showReplyModal(comment)"
                                                class="text-sm text-blue-600 hover:text-blue-800">
                                            Reply
                                        </button>
                                    </div>

                                    <!-- Replies -->
                                    <div v-if="comment.replies?.length" class="mt-4 pl-6 border-l-2 border-gray-200">
                                        <div v-for="reply in comment.replies"
                                             :key="reply.id"
                                             class="mt-3 first:mt-0">
                                            <div class="flex items-center justify-between">
                                                <h4 class="text-sm font-medium text-gray-900">
                                                    {{ reply.user.name }}
                                                    <span class="text-gray-500 text-xs ml-2">
                                                        {{ formatDate(reply.created_at) }}
                                                    </span>
                                                </h4>
                                            </div>
                                            <div class="mt-1 text-sm text-gray-700">
                                                {{ reply.content }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Pagination -->
            <pagination :links="comments.links" class="mt-6" />
        </div>

        <!-- Add Comment Modal -->
        <jet-dialog-modal :show="showAddCommentModal" @close="closeAddCommentModal">
            <template #title>Add Comment</template>
            <template #content>
                <form @submit.prevent="submitComment" class="space-y-4">
                    <!-- Comment Section -->
                    <div>
                        <jet-label for="comment_section" value="Comment Section" />
                        <select v-model="form.comment_section"
                                id="comment_section"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200">
                            <option value="">Select a section</option>
                            <option v-for="group in attributeGroups"
                                    :key="group.id"
                                    :value="group.id">
                                {{ group.name }}
                            </option>
                        </select>
                        <jet-input-error :message="form.errors.comment_section" class="mt-1" />
                    </div>

                    <!-- Comment Content -->
                    <div>
                        <jet-label for="comment" value="Comment" />
                        <textarea v-model="form.comment"
                                  id="comment"
                                  rows="4"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200"></textarea>
                        <jet-input-error :message="form.errors.comment" class="mt-1" />
                    </div>

                    <!-- File Attachments -->
                    <div class="space-y-2">
                        <div v-for="(doc, index) in form.documents"
                             :key="index"
                             class="flex items-center space-x-2">
                            <div class="flex-1">
                                <input type="file"
                                       @change="handleFileUpload($event, index)"
                                       class="block w-full text-sm text-gray-500
                                              file:mr-4 file:py-2 file:px-4
                                              file:rounded-md file:border-0
                                              file:text-sm file:font-semibold
                                              file:bg-blue-50 file:text-blue-700
                                              hover:file:bg-blue-100" />
                            </div>
                            <button type="button"
                                    @click="removeDocument(index)"
                                    class="text-red-600 hover:text-red-800">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <button type="button"
                                @click="addDocument"
                                class="text-sm text-blue-600 hover:text-blue-800">
                            + Add Attachment
                        </button>
                    </div>
                </form>
            </template>
            <template #footer>
                <jet-secondary-button @click="closeAddCommentModal">
                    Cancel
                </jet-secondary-button>
                <jet-button @click="submitComment"
                           :class="{ 'opacity-50': form.processing }"
                           :disabled="form.processing">
                    Submit Comment
                </jet-button>
            </template>
        </jet-dialog-modal>

        <!-- Reply Modal -->
        <jet-dialog-modal :show="showReplyCommentModal" @close="closeReplyModal">
            <template #title>Reply to Comment</template>
            <template #content>
                <!-- Original Comment -->
                <div class="mb-4 p-4 bg-gray-50 rounded-md">
                    <p class="text-sm text-gray-500">Replying to:</p>
                    <p class="mt-1 text-sm text-gray-700">{{ selectedComment?.content }}</p>
                </div>

                <form @submit.prevent="submitReply" class="space-y-4">
                    <!-- Reply Content -->
                    <div>
                        <jet-label for="reply" value="Your Reply" />
                        <textarea v-model="replyForm.content"
                                  id="reply"
                                  rows="4"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200"></textarea>
                        <jet-input-error :message="replyForm.errors.content" class="mt-1" />
                    </div>

                    <!-- File Attachments -->
                    <div class="space-y-2">
                        <div v-for="(attachment, index) in replyForm.attachments"
                             :key="index"
                             class="flex items-center space-x-2">
                            <div class="flex-1">
                                <input type="file"
                                       @change="handleReplyFileUpload($event, index)"
                                       class="block w-full text-sm text-gray-500
                                              file:mr-4 file:py-2 file:px-4
                                              file:rounded-md file:border-0
                                              file:text-sm file:font-semibold
                                              file:bg-blue-50 file:text-blue-700
                                              hover:file:bg-blue-100" />
                            </div>
                            <button type="button"
                                    @click="removeReplyAttachment(index)"
                                    class="text-red-600 hover:text-red-800">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <button type="button"
                                @click="addReplyAttachment"
                                class="text-sm text-blue-600 hover:text-blue-800">
                            + Add Attachment
                        </button>
                    </div>
                </form>
            </template>
            <template #footer>
                <jet-secondary-button @click="closeReplyModal">
                    Cancel
                </jet-secondary-button>
                <jet-button @click="submitReply"
                           :class="{ 'opacity-50': replyForm.processing }"
                           :disabled="replyForm.processing">
                    Submit Reply
                </jet-button>
            </template>
        </jet-dialog-modal>
    </app-layout>
</template>

<script>
import { ref, reactive } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from '@/Jetstream/Button.vue'
import JetInput from '@/Jetstream/Input.vue'
import JetInputError from '@/Jetstream/InputError.vue'
import JetLabel from '@/Jetstream/Label.vue'
import JetDialogModal from '@/Jetstream/DialogModal.vue'
import JetSecondaryButton from '@/Jetstream/SecondaryButton.vue'
import Pagination from '@/Jetstream/Pagination.vue'

export default {
    components: {
        AppLayout,
        JetButton,
        JetInput,
        JetInputError,
        JetLabel,
        JetDialogModal,
        JetSecondaryButton,
        Pagination,
    },

    props: {
        comments: Object,
        loanApplicationId: {
            type: String,
            required: true
        },
        attributeGroups: {
            type: Object,
            required: true
        }
    },

    setup(props) {
        const showAddCommentModal = ref(false)
        const showReplyCommentModal = ref(false)
        const selectedComment = ref(null)

        const form = reactive({
            comment_section: '',
            comment: '',
            documents: [{ file: null }],
            processing: false,
            errors: {}
        })

        const replyForm = reactive({
            content: '',
            attachments: [{ file: null }],
            processing: false,
            errors: {}
        })

        return {
            showAddCommentModal,
            showReplyCommentModal,
            selectedComment,
            form,
            replyForm
        }
    },

    methods: {
        formatDate(date) {
            return new Date(date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            })
        },

        getDownloadUrl(path) {
            return `/storage/${path}`
        },

        // Comment Methods
        addDocument() {
            this.form.documents.push({ file: null })
        },

        removeDocument(index) {
            this.form.documents.splice(index, 1)
        },

        handleFileUpload(event, index) {
            const file = event.target.files[0]
            if (file) {
                this.form.documents[index].file = file
            }
        },

        async submitComment() {
            const formData = new FormData()
            formData.append('loan_application_id', this.loanApplicationId)
            formData.append('comment_section', this.form.comment_section)
            formData.append('comment', this.form.comment)

            this.form.documents.forEach((doc, index) => {
                if (doc.file) {
                    formData.append(`documents[${index}][file]`, doc.file)
                }
            })

            try {
                await this.$inertia.post(route('loan_applications.save-comment'), formData, {
                    preserveScroll: true,
                    onSuccess: () => {
                        this.closeAddCommentModal()
                    }
                })
            } catch (error) {
                console.error('Error submitting comment:', error)
            }
        },

        closeAddCommentModal() {
                this.showAddCommentModal = false
                this.form.comment_section = ''
                this.form.comment = ''
                this.form.documents = [{ file: null }]
                this.form.errors = {}
            },

            // Reply Methods
            showReplyModal(comment) {
                this.selectedComment = comment
                this.showReplyCommentModal = true
                this.replyForm.content = ''
                this.replyForm.attachments = [{ file: null }]
            },

            closeReplyModal() {
                this.showReplyCommentModal = false
                this.selectedComment = null
                this.replyForm.content = ''
                this.replyForm.attachments = [{ file: null }]
                this.replyForm.errors = {}
            },

            addReplyAttachment() {
                this.replyForm.attachments.push({ file: null })
            },

            removeReplyAttachment(index) {
                this.replyForm.attachments.splice(index, 1)
            },

            handleReplyFileUpload(event, index) {
                const file = event.target.files[0]
                if (file) {
                    this.replyForm.attachments[index].file = file
                }
            },

            async submitReply() {
                const formData = new FormData()
                formData.append('comment_id', this.selectedComment.id)
                formData.append('content', this.replyForm.content)

                this.replyForm.attachments.forEach((attachment, index) => {
                    if (attachment.file) {
                        formData.append(`attachments[${index}]`, attachment.file)
                    }
                })

                try {
                    await this.$inertia.post(route('loan_applications.save-reply'), formData, {
                        preserveScroll: true,
                        onSuccess: () => {
                            this.closeReplyModal()
                        }
                    })
                } catch (error) {
                    console.error('Error submitting reply:', error)
                }
            }
        }
    }
</script>

<style scoped>
.btn-primary {
    @apply inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring focus:ring-blue-300 disabled:opacity-25 transition;
}
</style>
