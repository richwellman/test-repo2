<template>
    <div class="mapping-element">
        <span
            @click="handleClick"
            :class="['px-2', 'd-block', 'mapping-label', { active, disabled }]"
            role="button"
        >
            <span class="fw-regular">• {{ field }}</span>
            <span class="ms-2 fst-italic">({{ description }})</span>
        </span>
        <template v-if="disabled && disabled_reason">
            <span class="alert alert-warning d-block mt-2 small" role="alert">
                <small>{{ disabled_reason }}</small>
            </span>
        </template>
        <!--
            <template v-if="properties?.length > 0">
                <details>
                    <summary class="small text-muted">Select property...</summary>
                    <template v-for="property in properties" :key="property">
                        <MappingProperty
                            :field="field"
                            :property="property"
                            class="d-block ms-2"
                        />
                    </template>
                </details>
            </template>
        -->
    </div>
</template>

<script setup>
import { computed, inject } from 'vue'
import MappingProperty from './MappingProperty.vue'

const props = defineProps({
    category: { type: String, default: '' },
    description: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    disabled_reason: { type: String, default: '' },
    field: { type: String, default: '' },
    identifier: { type: Boolean, default: false },
    label: { type: String, default: '' },
    properties: { type: Array, default: () => [] },
    subcategory: { type: String, default: '' },
    temporal: { type: Boolean, default: false },
})
const onElementSelected = inject('onElementSelected')
const isActive = inject('isActive')

const handleClick = () => {
    if (props.disabled) return
    onElementSelected?.(props.field)
}

const active = computed(() => {
    if (!isActive) return false
    return isActive(props)
})

</script>

<style scoped>
.mapping-element > .mapping-label {
    cursor: pointer;
    word-break: break-word;
}
.mapping-element > .mapping-label.disabled {
    cursor: not-allowed;
    text-decoration: line-through;
    pointer-events: none;
    opacity: 0.7;
}
.active {
    background-color: #0d6efd;
    color: rgb(255 255 255);
}
</style>
