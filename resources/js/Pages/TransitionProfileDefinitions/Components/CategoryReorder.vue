<script setup>
import { ref, defineProps, defineEmits, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import { Dialog, DialogPanel, DialogTitle } from "@headlessui/vue";
import draggable from "vuedraggable";

// Props
const props = defineProps({
    profile: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        required: true,
    },
    isOpen: {
        type: Boolean,
        required: true,
    },
});

const emit = defineEmits(["onClose", "update:isOpen"]);

// Close modal
const closeModal = () => {
    emit("onClose");
    emit("update:isOpen", false);
};

// Form setup
const form = useForm({
    profile_id: props.profile.id,
    categories: [...props.categories],
});

const startCategories = ref([]);
const endCategories = ref([]);

// Ensure categories update when prop changes
watch(() => props.categories, (newCategories) => {
    console.log("Categories received:", JSON.parse(JSON.stringify(newCategories))); // Debugging

    startCategories.value = newCategories
        ? newCategories.filter(cat => cat.is_start_or_end === "start")
        : [];

    endCategories.value = newCategories
        ? newCategories.filter(cat => cat.is_start_or_end === "end")
        : [];
}, { immediate: true });



// Save updated categories
const submit = () => {

    startCategories.value.forEach((cat, index) => {
        cat.ordering_index = index + 1;
        cat.is_start_or_end = 'start';
    });
    endCategories.value.forEach((cat, index) => {
        cat.ordering_index = index + 1; 
        cat.is_start_or_end = 'end';
    });
    const updatedCategories = [
        ...startCategories.value,
        ...endCategories.value,
    ];
    form.categories = updatedCategories;

    form.post(`/transition-profile/categories/reorder`, {
        onSuccess: () => {
            closeModal();
        },
    });
};

</script>

<template>
    <Dialog :open="isOpen" @close="closeModal">
        <div class="fixed inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50">
            <DialogPanel class="bg-white p-6 rounded-lg w-96">
                <DialogTitle class="text-lg font-bold">Reorder Categories</DialogTitle>

                <!-- Start Categories -->
                <div class="mt-4">
                    <h2 class="text-md font-semibold mb-2">Start Categories</h2>
                    <draggable v-model="startCategories" item-key="id" class="space-y-2">
                        <template #item="{ element }">
                            <div v-if="element && element.category_name" class="p-2 border bg-gray-200 shadow-sm cursor-move">
                                {{ element.category_name }}
                            </div>
                            <div v-else class="text-red-500">No Name!</div>
                        </template>
                    </draggable>
                </div>

                <!-- End Categories -->
                <div class="mt-4">
                    <h2 class="text-md font-semibold mb-2">End Categories</h2>
                    <draggable v-model="endCategories" item-key="id" class="space-y-2">
                        <template #item="{ element }">
                            <div class="p-2 border bg-gray-200 shadow-sm cursor-move">
                                {{ element.category_name }}
                            </div>
                        </template>
                    </draggable>
                </div>

                <!-- Actions -->
                <div class="mt-4 flex justify-end space-x-2">
                    <button @click="closeModal" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
                    <button @click="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-500 text-white rounded">
                        {{ form.processing ? "Saving..." : "Save" }}
                    </button>
                </div>
            </DialogPanel>
        </div>
    </Dialog>
</template>
