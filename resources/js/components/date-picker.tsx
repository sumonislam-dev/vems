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
    const handleClear = () => {
        onChange('');
    };

    const formatDisplayDate = (dateString: string) => {
        if (!dateString) return '';
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            });
        } catch {
            return dateString;
        }
    };

    return (
        <div className={cn('space-y-2', className)}>
            <label htmlFor={name} className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {label}
                {required && <span className="ml-1 text-red-500">*</span>}
            </label>

            <div className="relative">
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
                        className={cn(
                            // Base styles
                            'w-full rounded-md border px-3 py-2 text-sm shadow-sm transition-all duration-200',
                            'bg-background text-foreground',
                            'placeholder:text-gray-500 dark:placeholder:text-gray-400',

                            // Border styles
                            error
                                ? 'border-red-300 focus:border-red-500 focus:ring-red-500/20 dark:border-red-600'
                                : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500/20 dark:border-gray-600',

                            // Focus styles
                            'focus:ring-2 focus:outline-none',

                            // Disabled styles
                            disabled && 'cursor-not-allowed bg-gray-50 opacity-50 dark:bg-gray-700',

                            // Hover styles
                            !disabled && 'hover:border-gray-400 hover:shadow-md dark:hover:border-gray-500',

                            // Date input specific styles
                            'relative pr-10',
                            '[&::-webkit-calendar-picker-indicator]:opacity-0',
                            '[&::-webkit-calendar-picker-indicator]:absolute',
                            '[&::-webkit-calendar-picker-indicator]:right-0',
                            '[&::-webkit-calendar-picker-indicator]:w-10',
                            '[&::-webkit-calendar-picker-indicator]:h-full',
                            '[&::-webkit-calendar-picker-indicator]:cursor-pointer',

                            // Dark mode date input styles
                            'dark:[color-scheme:dark]',

                            // Mobile improvements
                            'text-base sm:text-sm', // Prevent zoom on iOS
                            'min-h-[2.5rem]', // Better touch target
                        )}
                    />

                    {/* Custom calendar icon */}
                    <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                        <Calendar className="h-4 w-4 text-gray-400 dark:text-gray-500" />
                    </div>

                    {/* Clear button */}
                    {value && !disabled && (
                        <button
                            type="button"
                            onClick={handleClear}
                            className="absolute inset-y-0 right-9 flex items-center rounded p-1 text-gray-400 transition-colors duration-150 hover:bg-gray-100 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                            title="Clear date"
                        >
                            <X className="h-3 w-3" />
                        </button>
                    )}
                </div>

                {/* Display formatted date below input for better UX */}
                {value && (
                    <div className="mt-1 flex items-center text-xs text-gray-500 dark:text-gray-400">
                        <Calendar className="mr-1 h-3 w-3" />
                        <span>
                            Selected: <span className="font-medium text-gray-700 dark:text-gray-300">{formatDisplayDate(value)}</span>
                        </span>
                    </div>
                )}
            </div>

            {error && <p className="text-sm text-red-600 dark:text-red-400">{error}</p>}
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
        <div className={cn('space-y-2', className)}>
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
