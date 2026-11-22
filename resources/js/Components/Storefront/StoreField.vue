<script setup lang="ts">
defineOptions({ inheritAttrs: false });

const model = defineModel<string | null>({ required: true });
defineProps<{ id: string; label: string; error?: string; textarea?: boolean; required?: boolean; class?: string }>();
</script>

<template>
    <div :class="$props.class">
        <label :for="id" class="mb-1.5 block text-sm font-medium text-stone-700">
            {{ label }}<span v-if="required" class="text-brand-600" aria-hidden="true"> *</span>
        </label>
        <textarea
            v-if="textarea"
            :id="id"
            v-model="model"
            rows="3"
            v-bind="$attrs"
            :aria-invalid="!!error"
            :aria-describedby="error ? `${id}-error` : undefined"
            :class="['field', error ? 'ring-red-400' : '']"
        />
        <input
            v-else
            :id="id"
            v-model="model"
            v-bind="$attrs"
            :required="required"
            :aria-invalid="!!error"
            :aria-describedby="error ? `${id}-error` : undefined"
            :class="['field', error ? 'ring-red-400' : '']"
        />
        <p v-if="error" :id="`${id}-error`" class="mt-1.5 text-xs text-red-600">{{ error }}</p>
    </div>
</template>
