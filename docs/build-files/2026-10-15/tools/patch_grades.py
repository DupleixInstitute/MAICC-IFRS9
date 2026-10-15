R = 'C:/xampp/htdocs/MAICC-IFRS9/'

def patch(p, pairs):
    s = open(R + p, encoding='utf-8').read()
    for a, b in pairs:
        assert a in s, (p, a[:60])
        s = s.replace(a, b, 1)
    open(R + p, 'w', encoding='utf-8').write(s)

patch('app/Http/Controllers/InternalGradingController.php', [("""        public function toggle(InternalGradeProfile $profile)""", """    /**
     * Rename or re-describe a draft profile; an active profile is locked
     */
    public function updateProfile(Request $request, InternalGradeProfile $profile)
    {
        if ($profile->is_active) {
            return back()->with('error', 'The profile is active. Deactivate it before editing.');
        }
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string',
            'max_tenor_years' => 'required|integer|min:1|max:30',
        ]);
        $profile->update($validated);

        return back()->with('success', 'Profile updated');
    }

    /**
     * Delete a draft profile with its grades and PD curves
     */
    public function destroyProfile(InternalGradeProfile $profile)
    {
        if ($profile->is_active) {
            return back()->with('error', 'The profile is active. Deactivate it before deleting.');
        }
        $grades = $profile->mappings()->count();
        DB::transaction(function () use ($profile) {
            $profile->mappings()->delete(); // the PD curves go with their grade (cascade)
            $profile->delete();
        });
        AuditLoggerService::log(action: 'Internal Grade Profile Deleted', entityType: 'InternalGradeProfile', entityId: $profile->id,
            data: ['name' => $profile->name, 'grades' => $grades]);

        return redirect()->route('internal-grading.profiles')->with('success', "Profile {$profile->name} deleted with its {$grades} grade(s)");
    }

    /**
     * Delete a grade of a draft profile with its PD curve
     */
    public function destroyGrade(InternalGradeProfile $profile, InternalGradeMapping $grade)
    {
        abort_unless($grade->profile_id === $profile->id, 403);
        if ($profile->is_active) {
            return back()->with('error', 'The profile is active. Deactivate it before deleting a grade.');
        }
        $grade->delete();
        AuditLoggerService::log(action: 'Internal Grade Deleted', entityType: 'InternalGradeMapping', entityId: $grade->id,
            data: ['profile_id' => $profile->id, 'grade_code' => $grade->grade_code]);

        return back()->with('success', "Grade {$grade->grade_code} deleted");
    }

        public function toggle(InternalGradeProfile $profile)""")])

patch('routes/web.php', [("""        Route::put('/internal-grading/{profile}/grade/{grade}', [InternalGradingController::class, 'updateGrade'])
            ->name('internal-grading.grade.update');
""", """        Route::put('/internal-grading/{profile}/grade/{grade}', [InternalGradingController::class, 'updateGrade'])
            ->name('internal-grading.grade.update');
        Route::delete('/internal-grading/{profile}/grade/{grade}', [InternalGradingController::class, 'destroyGrade'])
            ->name('internal-grading.grade.destroy');
        Route::put('/internal-grading/{profile}', [InternalGradingController::class, 'updateProfile'])
            ->name('internal-grading.profile.update');
        Route::delete('/internal-grading/{profile}', [InternalGradingController::class, 'destroyProfile'])
            ->name('internal-grading.profile.destroy');
""")])

# Index.vue: labelled modal fields, edit and delete on each draft profile
patch('resources/js/Pages/InternalGrading/Index.vue', [
("""                            <div class="flex items-center space-x-2">
                                <!-- View Matrix -->""", """                            <div class="flex items-center space-x-2">
                                <button v-if="!profile.is_active" @click.stop="openEditProfile(profile)" class="text-gray-500 hover:text-maiic-600 transition" title="Edit profile">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button v-if="!profile.is_active" @click.stop="deleteProfile(profile)" class="text-gray-500 hover:text-red-600 transition" title="Delete profile">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <!-- View Matrix -->"""),
("""        <h3 class="text-lg font-semibold mb-4">Create Grading Profile</h3>

        <form @submit.prevent="submit" class="space-y-4">
            <input
                v-model="form.name"
                placeholder="Profile Name"
                class="w-full border rounded-lg px-3 py-2 focus:ring focus:ring-maiic-200"
            />

            <textarea
                v-model="form.description"
                placeholder="Description"
                class="w-full border rounded-lg px-3 py-2 focus:ring focus:ring-maiic-200"
            />
""", """        <h3 class="text-lg font-semibold mb-4">{{ editingId ? 'Edit Grading Profile' : 'Create Grading Profile' }}</h3>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Profile name</label>
                <input
                    v-model="form.name"
                    placeholder="e.g., MAIIC corporate grades"
                    class="w-full border rounded-lg px-3 py-2 focus:ring focus:ring-maiic-200"
                />
                <p v-if="formErrors.name" class="text-xs text-red-600 mt-1">{{ formErrors.name }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea
                    v-model="form.description"
                    placeholder="What the grades are for and where they come from"
                    class="w-full border rounded-lg px-3 py-2 focus:ring focus:ring-maiic-200"
                />
            </div>
"""),
("""                <p class="text-xs text-gray-500 mt-1">
                    Maximum number of years for PD curves. All grades in this profile will follow this limit.
                </p>""", """                <p class="text-xs text-gray-500 mt-1">
                    Maximum number of years for PD curves. All grades in this profile will follow this limit.
                </p>
                <p v-if="formErrors.max_tenor_years" class="text-xs text-red-600 mt-1">{{ formErrors.max_tenor_years }}</p>"""),
("""            <button type="button" class="primary-btn" @click="showModal = true">New profile</button>""",
 """            <button type="button" class="primary-btn" @click="openCreateProfile">New profile</button>"""),
("""const form = ref({
    name: '',
    description: '',
     max_tenor_years: '' ?? 5,
})""", """const form = ref({
    name: '',
    description: '',
    max_tenor_years: 5,
})
const editingId = ref(null)
const formErrors = ref({})

const openCreateProfile = () => {
    editingId.value = null
    formErrors.value = {}
    form.value = { name: '', description: '', max_tenor_years: 5 }
    showModal.value = true
}

const openEditProfile = (profile) => {
    editingId.value = profile.id
    formErrors.value = {}
    form.value = { name: profile.name, description: profile.description || '', max_tenor_years: profile.max_tenor_years }
    showModal.value = true
}

const deleteProfile = (profile) => {
    if (!confirm(`Delete the profile ${profile.name} and its ${profile.mappings_count || 0} grade(s)? This cannot be undone.`)) return
    router.delete(route('internal-grading.profile.destroy', profile.id), { preserveScroll: true })
}"""),
("""const submit = () => {
    router.post(route('internal-grading.profile.store'), form.value, {
        onSuccess: () => {
            showModal.value = false
            form.value = { name: '', description: '' }
        }
    })
}""", """const submit = () => {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false
            editingId.value = null
            form.value = { name: '', description: '', max_tenor_years: 5 }
        },
        onError: (e) => { formErrors.value = e },
    }
    if (editingId.value) {
        router.put(route('internal-grading.profile.update', editingId.value), form.value, opts)
    } else {
        router.post(route('internal-grading.profile.store'), form.value, opts)
    }
}"""),
])

patch('resources/js/Pages/InternalGrading/Grades.vue', [
("""               <i class="fas fa-edit "></i>
              </button>
""", """               <i class="fas fa-edit "></i>
              </button>
              <button
                v-if="!profile.is_active"
                @click.stop="deleteGrade(grade)"
                class="text-sm text-gray-500 hover:text-red-600"
                title="Delete grade"
              >
               <i class="fas fa-trash"></i>
              </button>
"""),
("""function closeModal() {""", """function deleteGrade(grade) {
  if (!confirm(`Delete grade ${grade.grade_code} and its PD curve? This cannot be undone.`)) return
  router.delete(route('internal-grading.grade.destroy', [props.profile.id, grade.id]), { preserveScroll: true })
}

function closeModal() {"""),
])
print('ok')
