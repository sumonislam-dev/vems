import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { Calendar, X } from 'lucide-react';

interface DatePickerProps {
    label: string;
    name: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    min?: string;
    max?: string;
    className?: string;
}

export function DatePicker({
    label,
    name,
    value,
    onChange,
    error,
    placeholder,
    required = false,
    disabled = false,
    min,
    max,
    className,
}: DatePickerProps) {
    return (
        <div className={cn('space-y-2', className)}>
            <Label htmlFor={name} className={cn(error && 'text-destructive')}>
                {label}
                {required && <span className="ml-1 text-destructive">*</span>}
            </Label>

            {/* Same look as Input; the native calendar button is stretched
                invisibly over the icon so clicking it opens the picker */}
            <div className="relative">
                <input
                    id={name}
                    name={name}
                    type="date"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={placeholder}
                    required={required}
                    disabled={disabled}
                    min={min}
                    max={max}
                    aria-invalid={!!error}
                    className={cn(
                        'flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 pr-16 text-base shadow-xs transition-[color,box-shadow] outline-none md:text-sm',
                        'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                        'aria-invalid:border-destructive aria-invalid:ring-destructive/20',
                        'disabled:cursor-not-allowed disabled:opacity-50 dark:[color-scheme:dark]',
                        '[&::-webkit-calendar-picker-indicator]:absolute [&::-webkit-calendar-picker-indicator]:right-0 [&::-webkit-calendar-picker-indicator]:h-full',
                        '[&::-webkit-calendar-picker-indicator]:w-9 [&::-webkit-calendar-picker-indicator]:cursor-pointer [&::-webkit-calendar-picker-indicator]:opacity-0',
                        !value && 'text-muted-foreground',
                    )}
                />
                <Calendar className="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-muted-foreground opacity-50" />
                {value && !disabled && (
                    <button
                        type="button"
                        onClick={() => onChange('')}
                        aria-label={`Clear ${label}`}
                        className="absolute top-1/2 right-9 -translate-y-1/2 rounded p-0.5 text-muted-foreground hover:text-foreground"
                    >
                        <X className="h-3.5 w-3.5" />
                    </button>
                )}
            </div>

            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}

// Enhanced FormField component specifically for dates
interface FormDateFieldProps {
    label: string;
    name: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    min?: string;
    max?: string;
    className?: string;
    helpText?: string;
}

export function FormDateField({
    label,
    name,
    value,
    onChange,
    error,
    placeholder,
    required = false,
    disabled = false,
    min,
    max,
    className,
    helpText,
}: FormDateFieldProps) {
    // Set reasonable defaults for vehicle document dates
    const defaultMin = min || '2000-01-01';
    const defaultMax = max || new Date(new Date().getFullYear() + 10, 11, 31).toISOString().split('T')[0];

    return (
        <div className={cn('space-y-1', className)}>
            <DatePicker
                label={label}
                name={name}
                value={value}
                onChange={onChange}
                error={error}
                placeholder={placeholder}
                required={required}
                disabled={disabled}
                min={defaultMin}
                max={defaultMax}
            />

            {helpText && !error && <p className="text-xs text-gray-500 dark:text-gray-400">{helpText}</p>}
        </div>
    );
}
