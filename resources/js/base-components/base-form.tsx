import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { Check, ChevronDown, FileText, Info, Upload, X } from 'lucide-react';
import * as React from 'react';

/**
 * Reusable Form Components for Laravel + Inertia.js applications
 *
 * Features:
 * - Integrates with Inertia's useForm hook
 * - Consistent styling and accessibility
 * - TypeScript support
 * - Renders an `error` string passed in from Inertia's errors or from
 *   `useFormValidation` (see hooks/use-form-validation.ts) — these
 *   components are presentational only and don't validate themselves
 *
 * @example
 * const { data, setData, post, processing, errors } = useForm({
 *   name: '',
 *   email: '',
 *   role: ''
 * });
 *
 * <BaseForm onSubmit={handleSubmit} processing={processing}>
 *   <FormField
 *     label="Name"
 *     name="name"
 *     value={data.name}
 *     onChange={(value) => setData('name', value)}
 *     error={errors.name}
 *     required
 *   />
 *   <FormField
 *     label="Email"
 *     name="email"
 *     type="email"
 *     value={data.email}
 *     onChange={(value) => setData('email', value)}
 *     error={errors.email}
 *     required
 *   />
 *   <FormSelect
 *     label="Role"
 *     name="role"
 *     value={data.role}
 *     onChange={(value) => setData('role', value)}
 *     error={errors.role}
 *     options={[
 *       { label: 'Admin', value: 'admin' },
 *       { label: 'User', value: 'user' }
 *     ]}
 *   />
 *   <FormActions>
 *     <Button type="submit">Save</Button>
 *   </FormActions>
 * </BaseForm>
 */

// Base Form Container
interface BaseFormProps extends React.FormHTMLAttributes<HTMLFormElement> {
    children: React.ReactNode;
    processing?: boolean;
    className?: string;
}

export function BaseForm({ children, processing = false, className, onSubmit, ...props }: BaseFormProps) {
    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (!processing && onSubmit) {
            onSubmit(e);
        }
    };

    return (
        <form className={cn('space-y-6', className)} onSubmit={handleSubmit} {...props}>
            <fieldset disabled={processing} className="space-y-6">
                {children}
            </fieldset>
        </form>
    );
}

// Form Field Container
interface FormFieldProps {
    label: string;
    name: string;
    value: string | number;
    onChange: (value: string) => void;
    error?: string;
    type?: 'text' | 'email' | 'password' | 'number' | 'tel' | 'url';
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    autoComplete?: string;
    autoFocus?: boolean;
    description?: string;
    className?: string;
    multiline?: boolean;
    rows?: number;
}

export function FormField({
    label,
    name,
    value,
    onChange,
    error,
    type = 'text',
    placeholder,
    required = false,
    disabled = false,
    autoComplete,
    autoFocus = false,
    description,
    className,
    multiline = false,
    rows = 4,
}: FormFieldProps) {
    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
        onChange(e.target.value);
    };

    const displayError = error;

    return (
        <div className={cn('space-y-2', className)}>
            <Label htmlFor={name} className={cn(displayError && 'text-destructive')}>
                {label}
                {required && <span className="ml-1 text-destructive">*</span>}
            </Label>

            {multiline ? (
                <textarea
                    id={name}
                    name={name}
                    value={value}
                    onChange={handleChange}
                    placeholder={placeholder}
                    required={required}
                    disabled={disabled}
                    autoFocus={autoFocus}
                    rows={rows}
                    className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    aria-invalid={!!displayError}
                    aria-describedby={description ? `${name}-description` : undefined}
                />
            ) : (
                <Input
                    id={name}
                    name={name}
                    type={type}
                    value={value}
                    onChange={handleChange}
                    placeholder={placeholder}
                    required={required}
                    disabled={disabled}
                    autoComplete={autoComplete}
                    autoFocus={autoFocus}
                    aria-invalid={!!displayError}
                    aria-describedby={description ? `${name}-description` : undefined}
                />
            )}

            {description && (
                <p id={`${name}-description`} className="text-sm text-muted-foreground">
                    {description}
                </p>
            )}

            <InputError message={displayError} />
        </div>
    );
}

// Form Select Component
interface SelectOption {
    label: string;
    value: string | number;
}

interface FormSelectProps {
    label: string;
    name: string;
    value: string | number;
    onChange: (value: string) => void;
    error?: string;
    options: SelectOption[];
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    description?: string;
    className?: string;
}

export function FormSelect({
    label,
    name,
    value,
    onChange,
    error,
    options,
    placeholder = 'Select an option...',
    required = false,
    disabled = false,
    description,
    className,
}: FormSelectProps) {
    return (
        <div className={cn('space-y-2', className)}>
            <Label htmlFor={name} className={cn(error && 'text-destructive')}>
                {label}
                {required && <span className="ml-1 text-destructive">*</span>}
            </Label>

            {/* An empty value only maps to 'none' when the list offers a blank
                option; otherwise '' lets the trigger show the placeholder */}
            <Select
                value={value.toString() || (options.some((option) => option.value.toString() === '') ? 'none' : '')}
                onValueChange={(selectedValue) => onChange(selectedValue === 'none' ? '' : selectedValue)}
                disabled={disabled}
                required={required}
            >
                <SelectTrigger id={name} aria-invalid={!!error} aria-describedby={description ? `${name}-description` : undefined}>
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value.toString() || 'none'}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {description && (
                <p id={`${name}-description`} className="text-sm text-muted-foreground">
                    {description}
                </p>
            )}

            <InputError message={error} />
        </div>
    );
}

// Form Multi-Select Component
interface FormMultiSelectProps {
    label: string;
    name: string;
    value: string[];
    onChange: (values: string[]) => void;
    error?: string;
    options: Array<{ value: string | number; label: string }>;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    description?: string;
    className?: string;
    searchable?: boolean;
    /** Extra help shown from an (i) icon beside the label */
    info?: string;
}

export function FormMultiSelect({
    label,
    name,
    value,
    onChange,
    error,
    options,
    placeholder = 'Select options...',
    required = false,
    disabled = false,
    description,
    className,
    searchable = true,
    info,
}: FormMultiSelectProps) {
    const [isOpen, setIsOpen] = React.useState(false);
    const [searchTerm, setSearchTerm] = React.useState('');
    const dropdownRef = React.useRef<HTMLDivElement>(null);

    // Filter options based on search term
    const filteredOptions = searchable ? options.filter((option) => option.label.toLowerCase().includes(searchTerm.toLowerCase())) : options;

    // Handle click outside to close dropdown
    React.useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setIsOpen(false);
                setSearchTerm('');
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const toggleOption = (optionValue: string) => {
        const newValue = value.includes(optionValue) ? value.filter((v) => v !== optionValue) : [...value, optionValue];
        onChange(newValue);
    };

    const getDisplayText = () => {
        if (value.length === 0) return placeholder;
        if (value.length === 1) {
            const selected = options.find((opt) => opt.value.toString() === value[0]);
            return selected?.label || placeholder;
        }
        if (value.length <= 3) {
            const selectedLabels = value
                .map((val) => {
                    const option = options.find((opt) => opt.value.toString() === val);
                    return option?.label;
                })
                .filter(Boolean);
            return selectedLabels.join(', ');
        }
        return `${value.length} selected`;
    };

    return (
        <div className={cn('space-y-2', className)} ref={dropdownRef}>
            <div className="flex items-center gap-1.5">
                <Label htmlFor={name} className={cn(error && 'text-destructive')}>
                    {label}
                    {required && <span className="ml-1 text-destructive">*</span>}
                </Label>
                {info && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <button type="button" aria-label={info} className="text-muted-foreground hover:text-foreground">
                                <Info className="h-3.5 w-3.5" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent className="max-w-xs">{info}</TooltipContent>
                    </Tooltip>
                )}
            </div>

            <div className="relative">
                <button
                    type="button"
                    id={name}
                    onClick={() => setIsOpen(!isOpen)}
                    disabled={disabled}
                    className={cn(
                        'flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background',
                        'placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none',
                        'disabled:cursor-not-allowed disabled:opacity-50',
                        error && 'border-destructive focus:ring-destructive',
                        isOpen && 'ring-2 ring-ring ring-offset-2',
                    )}
                    aria-expanded={isOpen}
                    aria-haspopup="listbox"
                    aria-invalid={!!error}
                    aria-describedby={description ? `${name}-description` : undefined}
                >
                    <span className={cn(value.length === 0 && 'text-muted-foreground')}>{getDisplayText()}</span>
                    <ChevronDown className="h-4 w-4 opacity-50" />
                </button>

                {isOpen && (
                    <div className="absolute z-50 mt-1 w-full rounded-md border bg-popover shadow-lg">
                        {searchable && (
                            <div className="border-b p-2">
                                <Input
                                    id={`${name}-search`}
                                    name={`${name}-search`}
                                    aria-label="Search options"
                                    type="text"
                                    placeholder="Search options..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="h-8"
                                />
                            </div>
                        )}

                        <div className="max-h-60 overflow-auto">
                            {filteredOptions.length === 0 ? (
                                <div className="px-3 py-2 text-sm text-muted-foreground">No options found</div>
                            ) : (
                                filteredOptions.map((option) => (
                                    <div
                                        key={option.value}
                                        onClick={() => toggleOption(option.value.toString())}
                                        className={cn(
                                            'flex cursor-pointer items-center space-x-2 px-3 py-2 text-sm hover:bg-accent hover:text-accent-foreground',
                                            value.includes(option.value.toString()) && 'bg-accent text-accent-foreground',
                                        )}
                                    >
                                        <div
                                            className={cn(
                                                'flex h-4 w-4 items-center justify-center rounded border border-primary',
                                                value.includes(option.value.toString()) && 'bg-primary text-primary-foreground',
                                            )}
                                        >
                                            {value.includes(option.value.toString()) && <Check className="h-3 w-3" />}
                                        </div>
                                        <span>{option.label}</span>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                )}
            </div>

            {description && (
                <p id={`${name}-description`} className="text-sm text-muted-foreground">
                    {description}
                </p>
            )}

            <InputError message={error} />
        </div>
    );
}

// Form Checkbox Component
interface FormCheckboxProps {
    label: string;
    name: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    error?: string;
    disabled?: boolean;
    description?: string;
    className?: string;
}

export function FormCheckbox({ label, name, checked, onChange, error, disabled = false, description, className }: FormCheckboxProps) {
    return (
        <div className={cn('space-y-2', className)}>
            <div className="flex items-center space-x-2">
                <Checkbox
                    id={name}
                    name={name}
                    checked={checked}
                    onCheckedChange={onChange}
                    disabled={disabled}
                    aria-invalid={!!error}
                    aria-describedby={description ? `${name}-description` : undefined}
                />
                <Label htmlFor={name} className="text-sm font-normal">
                    {label}
                </Label>
            </div>

            {description && (
                <p id={`${name}-description`} className="text-sm text-muted-foreground">
                    {description}
                </p>
            )}

            <InputError message={error} />
        </div>
    );
}

// Form Actions Container
interface FormActionsProps {
    children: React.ReactNode;
    className?: string;
    align?: 'left' | 'right' | 'center';
}

export function FormActions({ children, className, align = 'left' }: FormActionsProps) {
    return (
        <div
            className={cn(
                'flex gap-3 pt-4',
                align === 'right' && 'justify-end',
                align === 'center' && 'justify-center',
                align === 'left' && 'justify-start',
                className,
            )}
        >
            {children}
        </div>
    );
}

// Form Textarea Component
interface FormTextareaProps {
    label: string;
    name: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    description?: string;
    rows?: number;
    maxLength?: number;
    className?: string;
}

export function FormTextarea({
    label,
    name,
    value,
    onChange,
    error,
    placeholder,
    required = false,
    disabled = false,
    description,
    rows = 3,
    maxLength,
    className,
}: FormTextareaProps) {
    const handleChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
        onChange(e.target.value);
    };

    return (
        <div className={cn('space-y-2', className)}>
            <Label htmlFor={name}>
                {label}
                {required && <span className="ml-1 text-destructive">*</span>}
            </Label>

            <textarea
                id={name}
                name={name}
                value={value}
                onChange={handleChange}
                placeholder={placeholder}
                required={required}
                disabled={disabled}
                rows={rows}
                maxLength={maxLength}
                aria-invalid={!!error}
                aria-describedby={description ? `${name}-description` : undefined}
                className={cn(
                    'flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                    'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                )}
            />

            {maxLength && (
                <div className="flex justify-between text-xs text-muted-foreground">
                    <span>{description}</span>
                    <span>
                        {value.length}/{maxLength}
                    </span>
                </div>
            )}

            {description && !maxLength && (
                <p id={`${name}-description`} className="text-sm text-muted-foreground">
                    {description}
                </p>
            )}

            <InputError message={error} />
        </div>
    );
}

// Form Section Component for grouping related fields
interface FormSectionProps {
    title?: string;
    description?: string;
    children: React.ReactNode;
    className?: string;
}

export function FormSection({ title, description, children, className }: FormSectionProps) {
    return (
        <div className={cn('space-y-4', className)}>
            {(title || description) && (
                <div className="space-y-1">
                    {title && <h3 className="text-lg leading-6 font-medium">{title}</h3>}
                    {description && <p className="text-sm text-muted-foreground">{description}</p>}
                </div>
            )}
            <div className="space-y-4">{children}</div>
        </div>
    );
}

// Form Date Picker Component
interface FormDatePickerProps {
    label: string;
    name: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    description?: string;
    min?: string;
    max?: string;
    className?: string;
}

export function FormDatePicker({
    label,
    name,
    value,
    onChange,
    error,
    placeholder,
    required = false,
    disabled = false,
    description,
    min,
    max,
    className,
}: FormDatePickerProps) {
    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        onChange(e.target.value);
    };

    return (
        <div className={cn('space-y-2', className)}>
            <Label htmlFor={name} className={cn(error && 'text-destructive')}>
                {label}
                {required && <span className="ml-1 text-destructive">*</span>}
            </Label>

            <div className="relative">
                <Input
                    id={name}
                    name={name}
                    type="date"
                    value={value}
                    onChange={handleChange}
                    placeholder={placeholder}
                    required={required}
                    disabled={disabled}
                    min={min}
                    max={max}
                    aria-invalid={!!error}
                    aria-describedby={description ? `${name}-description` : undefined}
                />
            </div>

            {description && (
                <p id={`${name}-description`} className="text-sm text-muted-foreground">
                    {description}
                </p>
            )}

            <InputError message={error} />
        </div>
    );
}

// Form File Upload Component
interface FormFileUploadProps {
    label: string;
    name: string;
    value?: File | null;
    onChange: (file: File | null) => void;
    error?: string;
    accept?: string;
    required?: boolean;
    disabled?: boolean;
    description?: string;
    maxSize?: number; // in bytes
    className?: string;
    compact?: boolean;
}

function formatFileSize(bytes: number) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/** "image/*,.pdf" -> "Image or PDF" */
function describeAccept(accept: string) {
    const names = accept.split(',').map((part) => {
        const type = part.trim();
        if (type.endsWith('/*')) return type.slice(0, -2).replace(/^./, (c) => c.toUpperCase());
        return type.replace(/^\./, '').toUpperCase();
    });
    return names.length > 1 ? `${names.slice(0, -1).join(', ')} or ${names[names.length - 1]}` : names[0];
}

export function FormFileUpload({
    label,
    name,
    value,
    onChange,
    error,
    accept,
    required = false,
    disabled = false,
    description,
    maxSize,
    className,
    compact = true,
}: FormFileUploadProps) {
    const fileInputRef = React.useRef<HTMLInputElement>(null);
    const [dragActive, setDragActive] = React.useState(false);
    const [sizeError, setSizeError] = React.useState<string>();

    const pick = (file: File | null) => {
        if (file && maxSize && file.size > maxSize) {
            setSizeError(`${file.name} is larger than ${formatFileSize(maxSize)}.`);
            if (fileInputRef.current) fileInputRef.current.value = '';
            return;
        }
        setSizeError(undefined);
        onChange(file);
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => pick(e.target.files?.[0] || null);

    const handleDrag = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.type === 'dragenter' || e.type === 'dragover') {
            setDragActive(true);
        } else if (e.type === 'dragleave') {
            setDragActive(false);
        }
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setDragActive(false);

        if (disabled) return;

        pick(e.dataTransfer.files[0] || null);
    };

    const removeFile = () => {
        onChange(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const hint = [accept && describeAccept(accept), maxSize && `max ${formatFileSize(maxSize)}`].filter(Boolean).join(' · ');

    if (compact) {
        return (
            <div className={cn('space-y-2', className)}>
                <Label htmlFor={name} className={cn(error && 'text-destructive')}>
                    {label}
                    {required && <span className="ml-1 text-destructive">*</span>}
                </Label>
                <div
                    className={cn(
                        'relative flex h-9 w-full items-center gap-2 rounded-md border px-3 text-sm shadow-xs transition-[color,box-shadow]',
                        'focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50',
                        dragActive ? 'border-primary bg-primary/5' : 'border-input',
                        disabled ? 'cursor-not-allowed opacity-50' : 'hover:bg-accent/50',
                        (error || sizeError) && 'border-destructive',
                    )}
                    onDragEnter={handleDrag}
                    onDragLeave={handleDrag}
                    onDragOver={handleDrag}
                    onDrop={handleDrop}
                >
                    <input
                        ref={fileInputRef}
                        id={name}
                        name={name}
                        type="file"
                        accept={accept}
                        onChange={handleFileChange}
                        required={required}
                        disabled={disabled}
                        className="absolute inset-0 h-full w-full cursor-pointer opacity-0 disabled:cursor-not-allowed"
                        aria-describedby={description ? `${name}-description` : undefined}
                    />
                    {value ? (
                        <>
                            <FileText className="h-4 w-4 shrink-0 text-primary" />
                            <span className="min-w-0 flex-1 truncate font-medium">{value.name}</span>
                            <span className="shrink-0 text-xs text-muted-foreground">{formatFileSize(value.size)}</span>
                            <button
                                type="button"
                                onClick={removeFile}
                                disabled={disabled}
                                aria-label={`Remove ${value.name}`}
                                className="relative z-10 shrink-0 rounded p-0.5 text-muted-foreground hover:text-destructive"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </>
                    ) : (
                        <>
                            <Upload className="h-4 w-4 shrink-0 text-muted-foreground" />
                            <span className="min-w-0 flex-1 truncate text-muted-foreground">Choose file or drop here</span>
                            {hint && <span className="shrink-0 text-xs text-muted-foreground">{hint}</span>}
                        </>
                    )}
                </div>
                {description && (
                    <p id={`${name}-description`} className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
                <InputError message={sizeError ?? error} />
            </div>
        );
    }

    return (
        <div className={cn('space-y-2', className)}>
            <Label htmlFor={name} className={cn(error && 'text-destructive')}>
                {label}
                {required && <span className="ml-1 text-destructive">*</span>}
            </Label>

            <div
                className={cn(
                    'relative rounded-lg border-2 border-dashed transition-colors',
                    'p-6',
                    dragActive ? 'border-primary bg-primary/5' : 'border-border',
                    disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer hover:border-primary/50',
                    error && 'border-destructive',
                )}
                onDragEnter={handleDrag}
                onDragLeave={handleDrag}
                onDragOver={handleDrag}
                onDrop={handleDrop}
                onClick={() => !disabled && fileInputRef.current?.click()}
            >
                <input
                    ref={fileInputRef}
                    id={name}
                    name={name}
                    type="file"
                    accept={accept}
                    onChange={handleFileChange}
                    required={required}
                    disabled={disabled}
                    className="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                    aria-describedby={description ? `${name}-description` : undefined}
                />

                {value ? (
                    <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-2">
                            <div className="text-sm">
                                <p className="font-medium">{value.name}</p>
                                <p className="text-muted-foreground">{formatFileSize(value.size)}</p>
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={(e) => {
                                e.stopPropagation();
                                removeFile();
                            }}
                            disabled={disabled}
                        >
                            <X className="h-4 w-4" />
                        </Button>
                    </div>
                ) : (
                    <div className="text-center">
                        <Upload className="mx-auto h-12 w-12 text-muted-foreground" />
                        <div className="mt-2">
                            <p className="text-sm font-medium">Click to upload or drag and drop</p>
                            {accept && <p className="mt-1 text-xs text-muted-foreground">{describeAccept(accept)}</p>}
                            {maxSize && <p className="mt-1 text-xs text-muted-foreground">Max size: {formatFileSize(maxSize)}</p>}
                        </div>
                    </div>
                )}
            </div>

            {description && (
                <p id={`${name}-description`} className="text-sm text-muted-foreground">
                    {description}
                </p>
            )}

            <InputError message={sizeError ?? error} />
        </div>
    );
}
