<script setup lang="ts">
import { Loader2 } from 'lucide-vue-next'

export interface FormFieldOption {
  label: string
  value: string | number
  /** Class applied to the button when this option is active (for toggle type) */
  activeClass?: string
}

export interface FormField {
  key: string
  label: string
  type: 'text' | 'number' | 'select' | 'multiselect' | 'textarea' | 'date' | 'toggle'
  required?: boolean
  placeholder?: string
  /** Initial value emitted to parent when modelValue[key] is undefined */
  defaultValue?: any
  /** Helper text shown below the input */
  description?: string
  /** Column span in the 2-col grid: 1 = half width, 2 = full width (default) */
  colSpan?: 1 | 2
  /** Return false to hide the field (also skips validation) */
  condition?: (data: Record<string, any>) => boolean
  options?: FormFieldOption[]
  /** For number fields */
  min?: number
  max?: number
  /** For text / textarea fields */
  maxLength?: number
  /** For textarea */
  rows?: number
  /** Custom validator — return error string or null */
  validation?: (value: any) => string | null
}

const props = withDefaults(defineProps<{
  fields: FormField[]
  modelValue: Record<string, any>
  submitLabel?: string
  loading?: boolean
  hideSubmit?: boolean
  /** Extra classes applied to the submit button */
  submitClass?: string
}>(), {
  submitLabel: 'Submit',
  loading: false,
  hideSubmit: false,
})

const emit = defineEmits<{
  'update:modelValue': [Record<string, any>]
  'submit': [Record<string, any>]
}>()

const errors  = ref<Record<string, string>>({})
const touched = ref<Set<string>>(new Set())

const visibleFields = computed(() =>
  props.fields.filter(f => !f.condition || f.condition(props.modelValue))
)

function updateField(key: string, value: any) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
  if (touched.value.has(key)) {
    validateField(key, value)
  }
}

function validateField(key: string, value: any): string | null {
  const field = props.fields.find(f => f.key === key)
  if (!field) return null

  // Skip validation for hidden fields
  if (field.condition && !field.condition(props.modelValue)) {
    delete errors.value[key]
    return null
  }

  const isEmpty = Array.isArray(value)
    ? value.length === 0
    : value === null || value === undefined || String(value).trim() === ''

  if (field.required && isEmpty) {
    errors.value[key] = `${field.label} is required`
    return errors.value[key]
  }

  if (isEmpty) {
    delete errors.value[key]
    return null
  }

  if (field.type === 'number') {
    const num = Number(value)
    if (isNaN(num)) {
      errors.value[key] = `${field.label} must be a valid number`
      return errors.value[key]
    }
    if (field.min !== undefined && num < field.min) {
      errors.value[key] = `${field.label} must be at least ${field.min}`
      return errors.value[key]
    }
    if (field.max !== undefined && num > field.max) {
      errors.value[key] = `${field.label} must be at most ${field.max}`
      return errors.value[key]
    }
  }

  if (field.maxLength !== undefined && String(value).length > field.maxLength) {
    errors.value[key] = `${field.label} must be ${field.maxLength} characters or less`
    return errors.value[key]
  }

  if (field.validation) {
    const customError = field.validation(value)
    if (customError) {
      errors.value[key] = customError
      return customError
    }
  }

  delete errors.value[key]
  return null
}

function validate(): boolean {
  errors.value = {}
  let valid = true
  for (const field of visibleFields.value) {
    if (validateField(field.key, props.modelValue[field.key])) valid = false
  }
  return valid
}

function handleBlur(key: string) {
  touched.value.add(key)
  validateField(key, props.modelValue[key])
}

function handleSubmit() {
  visibleFields.value.forEach(f => touched.value.add(f.key))
  if (!validate()) return
  emit('submit', { ...props.modelValue })
}

function reset() {
  errors.value = {}
  touched.value.clear()
}

defineExpose({ validate, reset })

onMounted(() => {
  const defaults: Record<string, any> = {}
  let hasDefaults = false
  for (const field of props.fields) {
    if (field.defaultValue !== undefined && props.modelValue[field.key] === undefined) {
      defaults[field.key] = field.defaultValue
      hasDefaults = true
    }
  }
  if (hasDefaults) {
    emit('update:modelValue', { ...props.modelValue, ...defaults })
  }
})
</script>

<template>
  <form @submit.prevent="handleSubmit" class="grid grid-cols-2 gap-x-3 gap-y-4" novalidate>
    <div
      v-for="field in visibleFields"
      :key="field.key"
      class="space-y-1.5"
      :class="field.colSpan === 1 ? 'col-span-1' : 'col-span-2'"
    >
      <!-- Label (hidden for toggle — it has its own visual structure) -->
      <label v-if="field.type !== 'toggle'" class="text-xs text-muted-foreground block">
        {{ field.label }}
        <span v-if="field.required" class="text-destructive ml-0.5">*</span>
      </label>

      <!-- Toggle -->
      <div
        v-if="field.type === 'toggle'"
        class="flex gap-1 bg-muted rounded-lg p-1"
      >
        <button
          v-for="opt in field.options"
          :key="opt.value"
          type="button"
          class="flex-1 py-1.5 rounded-md text-sm font-medium transition-all duration-150 flex items-center justify-center"
          :class="(modelValue[field.key] ?? field.defaultValue) === opt.value
            ? (opt.activeClass ?? 'bg-background text-foreground shadow-sm')
            : 'text-muted-foreground hover:text-foreground'"
          @click="updateField(field.key, opt.value)"
        >
          {{ opt.label }}
        </button>
      </div>

      <!-- Text -->
      <Input
        v-else-if="field.type === 'text'"
        :model-value="modelValue[field.key] ?? field.defaultValue ?? ''"
        @update:model-value="val => updateField(field.key, val)"
        @blur="handleBlur(field.key)"
        type="text"
        :placeholder="field.placeholder"
        :maxlength="field.maxLength"
        :class="errors[field.key] ? 'border-destructive focus-visible:ring-destructive' : ''"
      />

      <!-- Number -->
      <Input
        v-else-if="field.type === 'number'"
        :model-value="modelValue[field.key] ?? field.defaultValue ?? ''"
        @update:model-value="val => updateField(field.key, val)"
        @blur="handleBlur(field.key)"
        type="number"
        :placeholder="field.placeholder"
        :min="field.min"
        :max="field.max"
        :class="errors[field.key] ? 'border-destructive focus-visible:ring-destructive' : ''"
      />

      <!-- Date -->
      <Input
        v-else-if="field.type === 'date'"
        :model-value="modelValue[field.key] ?? field.defaultValue ?? ''"
        @update:model-value="val => updateField(field.key, val)"
        @blur="handleBlur(field.key)"
        type="date"
        :class="errors[field.key] ? 'border-destructive focus-visible:ring-destructive' : ''"
      />

      <!-- Textarea -->
      <Textarea
        v-else-if="field.type === 'textarea'"
        :model-value="modelValue[field.key] ?? field.defaultValue ?? ''"
        @update:model-value="val => updateField(field.key, val)"
        @blur="handleBlur(field.key)"
        :placeholder="field.placeholder"
        :rows="field.rows ?? 3"
        :maxlength="field.maxLength"
        :class="errors[field.key] ? 'border-destructive focus-visible:ring-destructive' : ''"
      />

      <!-- Select -->
      <Select
        v-else-if="field.type === 'select'"
        :model-value="(modelValue[field.key] ?? field.defaultValue) != null ? String(modelValue[field.key] ?? field.defaultValue) : undefined"
        @update:model-value="val => { updateField(field.key, val); handleBlur(field.key) }"
      >
        <SelectTrigger :class="errors[field.key] ? 'border-destructive focus:ring-destructive' : ''">
          <SelectValue :placeholder="field.placeholder ?? `Select ${field.label.toLowerCase()}...`" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="opt in field.options"
            :key="opt.value"
            :value="String(opt.value)"
          >
            {{ opt.label }}
          </SelectItem>
        </SelectContent>
      </Select>

      <!-- MultiSelect -->
      <MultiSelect
        v-else-if="field.type === 'multiselect'"
        :model-value="modelValue[field.key] ?? field.defaultValue ?? []"
        @update:model-value="val => { updateField(field.key, val); handleBlur(field.key) }"
        :options="field.options ?? []"
        :placeholder="field.placeholder ?? `Select ${field.label.toLowerCase()}...`"
        :error="!!errors[field.key]"
      />

      <!-- Error message -->
      <p v-if="errors[field.key]" class="text-xs text-destructive">
        {{ errors[field.key] }}
      </p>

      <!-- Description -->
      <p v-if="field.description" class="text-xs text-muted-foreground">
        {{ field.description }}
      </p>
    </div>

    <div class="col-span-2">
      <Button
        v-if="!hideSubmit"
        type="submit"
        class="w-full"
        :class="submitClass"
        :disabled="loading"
      >
        <Loader2 v-if="loading" class="w-4 h-4 mr-2 animate-spin" />
        {{ submitLabel }}
      </Button>
    </div>
  </form>
</template>
