# Reusable Form Components

This directory contains reusable form components designed to work seamlessly with Laravel + Inertia.js applications. The components provide consistent styling, validation, and accessibility features while reducing boilerplate code.

## Components Overview

### 1. Base Form Components (`base-form.tsx`)

Simple, lightweight form components that integrate directly with Inertia's `useForm` hook.

**Features:**

- ✅ Works with existing Inertia.js setup
- ✅ Built-in client-side validation
- ✅ Consistent styling with shadcn/ui
- ✅ TypeScript support
- ✅ Accessibility features
- ✅ Loading states and error handling

### 2. Advanced Form Components (`advanced-form.tsx`)

More powerful form components that integrate with React Hook Form for complex validation scenarios.

**Features:**

- ✅ Schema-based validation (Zod, Yup)
- ✅ Better performance for large forms
- ✅ Advanced form features (field arrays, conditional fields)
- ⚠️ Requires additional dependencies

## Quick Start

### Basic Usage with Inertia.js

```tsx
import { useForm } from '@inertiajs/react';
import { BaseForm, FormField, FormSelect, FormActions } from '@/base-components/base-form';

export default function UserForm() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        role: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('users.store'));
    };

    return (
        <BaseForm onSubmit={submit} processing={processing}>
            <FormField label="Name" name="name" value={data.name} onChange={(value) => setData('name', value)} error={errors.name} required />

            <FormField
                label="Email"
                name="email"
                type="email"
                value={data.email}
                onChange={(value) => setData('email', value)}
                error={errors.email}
                required
            />

            <FormSelect
                label="Role"
                name="role"
                value={data.role}
                onChange={(value) => setData('role', value)}
                error={errors.role}
                options={[
                    { label: 'Admin', value: 'admin' },
                    { label: 'User', value: 'user' },
                ]}
            />

            <FormActions>
                <Button type="submit">Save</Button>
            </FormActions>
        </BaseForm>
    );
}
```

## Available Components

### BaseForm

Main form container that handles submission and loading states.

```tsx
<BaseForm onSubmit={handleSubmit} processing={processing}>
    {/* Form fields */}
</BaseForm>
```

### FormField

Standard input field with label, validation, and error display.

```tsx
<FormField
    label="Email"
    name="email"
    type="email"
    value={data.email}
    onChange={(value) => setData('email', value)}
    error={errors.email}
    required
    placeholder="user@example.com"
    description="We'll never share your email"
    validation={[validationRules.required(), validationRules.email()]}
/>
```

### FormSelect

Dropdown select field.

```tsx
<FormSelect
    label="Country"
    name="country"
    value={data.country}
    onChange={(value) => setData('country', value)}
    error={errors.country}
    options={[
        { label: 'United States', value: 'us' },
        { label: 'Canada', value: 'ca' },
    ]}
    placeholder="Select a country..."
/>
```

### FormTextarea

Multi-line text input.

```tsx
<FormTextarea
    label="Description"
    name="description"
    value={data.description}
    onChange={(value) => setData('description', value)}
    error={errors.description}
    rows={4}
    maxLength={500}
    placeholder="Enter description..."
/>
```

### FormCheckbox

Checkbox input with label.

```tsx
<FormCheckbox
    label="Subscribe to newsletter"
    name="newsletter"
    checked={data.newsletter}
    onChange={(checked) => setData('newsletter', checked)}
    error={errors.newsletter}
    description="Receive weekly updates"
/>
```

### FormSection

Groups related form fields with optional title and description.

```tsx
<FormSection
  title="Personal Information"
  description="Enter your basic details"
>
  <FormField ... />
  <FormField ... />
</FormSection>
```

### FormActions

Container for form buttons with automatic loading states.

```tsx
<FormActions align="right">
    <Button type="button" variant="outline">
        Cancel
    </Button>
    <Button type="submit">Save</Button>
</FormActions>
```

## Client-Side Validation

The base form components include built-in validation rules:

```tsx
import { validationRules } from '@/base-components/base-form';

<FormField
    // ... other props
    validation={[
        validationRules.required('Name is required'),
        validationRules.minLength(2, 'Must be at least 2 characters'),
        validationRules.maxLength(50, 'Must be less than 50 characters'),
        validationRules.email('Please enter a valid email'),
        validationRules.pattern(/^[A-Z]/, 'Must start with uppercase letter'),
    ]}
/>;
```

## Advanced Usage with React Hook Form

For complex forms, you can use the advanced components with React Hook Form:

```bash
npm install react-hook-form @hookform/resolvers zod
```

```tsx
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { AdvancedForm, AdvancedFormField } from '@/base-components/advanced-form';

const schema = z.object({
    name: z.string().min(1, 'Name is required'),
    email: z.string().email('Invalid email'),
});

export default function AdvancedUserForm() {
    const form = useForm({
        resolver: zodResolver(schema),
        defaultValues: { name: '', email: '' },
    });

    const onSubmit = (data) => {
        // Handle form submission
    };

    return (
        <AdvancedForm form={form} onSubmit={onSubmit}>
            <AdvancedFormField form={form} name="name" label="Name" render={({ field }) => <Input {...field} />} />
        </AdvancedForm>
    );
}
```

## Form Builder for Dynamic Forms

For completely dynamic forms, use the FormBuilder component:

```tsx
import { FormBuilder } from '@/base-components/advanced-form';

const formFields = [
    {
        name: 'name',
        label: 'Full Name',
        type: 'text',
        required: true,
        placeholder: 'Enter your name',
    },
    {
        name: 'role',
        label: 'Role',
        type: 'select',
        required: true,
        options: [
            { label: 'Admin', value: 'admin' },
            { label: 'User', value: 'user' },
        ],
    },
];

<FormBuilder fields={formFields} onSubmit={handleSubmit} processing={processing} />;
```

## Best Practices

1. **Use BaseForm for most cases** - It integrates perfectly with Inertia.js
2. **Group related fields** - Use FormSection to organize complex forms
3. **Add client-side validation** - Improve UX with immediate feedback
4. **Use TypeScript** - Define form data types for better development experience
5. **Handle loading states** - The components automatically handle this
6. **Provide helpful descriptions** - Guide users with field descriptions

## Migration from Existing Forms

To migrate existing forms:

1. Replace manual field groups with FormField components
2. Remove manual onChange handlers
3. Replace InputError with built-in error handling
4. Wrap form in BaseForm component
5. Use FormActions for buttons

Before:

```tsx
<div className="grid gap-2">
    <Label htmlFor="name">Name</Label>
    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} disabled={processing} />
    <InputError message={errors.name} />
</div>
```

After:

```tsx
<FormField label="Name" name="name" value={data.name} onChange={(value) => setData('name', value)} error={errors.name} />
```

## Examples

See `resources/js/examples/form-example.tsx` for a complete working example.
