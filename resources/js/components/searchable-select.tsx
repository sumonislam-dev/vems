import { ChevronDown, Search, X } from 'lucide-react';
import React, { useEffect, useRef, useState } from 'react';

interface Option {
    label: string;
    value: string;
}

interface SearchableSelectProps {
    label: string;
    name: string;
    value: string;
    onChange: (value: string) => void;
    options: Option[];
    error?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    /** Keep the label for screen readers only, e.g. when a heading beside it already names the field */
    hideLabel?: boolean;
}

export function SearchableSelect({
    label,
    name,
    value,
    onChange,
    options,
    error,
    placeholder = 'Search and select...',
    required = false,
    disabled = false,
    hideLabel = false,
}: SearchableSelectProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [filteredOptions, setFilteredOptions] = useState(options);
    const dropdownRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    // Get the display value
    const selectedOption = options.find((option) => String(option.value) === String(value));
    const displayValue = selectedOption ? selectedOption.label : '';

    // Filter options based on search term
    useEffect(() => {
        if (searchTerm) {
            const filtered = options.filter((option) => option.label.toLowerCase().includes(searchTerm.toLowerCase()));
            setFilteredOptions(filtered);
        } else {
            setFilteredOptions(options);
        }
    }, [searchTerm, options]);

    // Close dropdown when clicking outside
    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setIsOpen(false);
                setSearchTerm('');
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, []);

    const handleToggle = () => {
        if (!disabled) {
            setIsOpen(!isOpen);
            if (!isOpen) {
                setTimeout(() => inputRef.current?.focus(), 100);
            }
        }
    };

    const handleSelect = (option: Option) => {
        onChange(option.value);
        setIsOpen(false);
        setSearchTerm('');
    };

    const handleClear = (e: React.MouseEvent) => {
        e.stopPropagation();
        onChange('none');
        setSearchTerm('');
    };

    return (
        <div className="space-y-2">
            {/* The trigger is a div, not a form control, so this is a plain
                span it points to with aria-labelledby (a <label> would need one) */}
            <span id={`${name}-label`} className={hideLabel ? 'sr-only' : 'block text-sm leading-none font-medium'}>
                {label}
                {required && <span className="ml-1 text-red-500">*</span>}
            </span>

            <div className={hideLabel ? 'relative !mt-0' : 'relative'} ref={dropdownRef}>
                <div
                    className={`relative flex h-9 w-full cursor-pointer items-center rounded-md border bg-transparent px-3 text-left text-sm shadow-xs transition-[color,box-shadow] focus-within:ring-[3px] ${
                        error
                            ? 'border-destructive focus-within:ring-destructive/20'
                            : 'border-input focus-within:border-ring focus-within:ring-ring/50'
                    } ${disabled ? 'cursor-not-allowed opacity-50' : ''}`}
                    onClick={handleToggle}
                    role="combobox"
                    aria-labelledby={`${name}-label`}
                    aria-expanded={isOpen}
                    aria-invalid={!!error}
                    aria-disabled={disabled}
                >
                    {isOpen ? (
                        <div className="flex w-full items-center">
                            <Search className="mr-2 h-4 w-4 text-muted-foreground" />
                            <input
                                ref={inputRef}
                                type="text"
                                className="min-w-0 flex-1 border-none bg-transparent p-0 text-sm placeholder:text-muted-foreground focus:ring-0 focus:outline-none"
                                id={`${name}-search`}
                                name={`${name}-search`}
                                placeholder="Type to search..."
                                aria-labelledby={`${name}-label`}
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                onClick={(e) => e.stopPropagation()}
                            />
                        </div>
                    ) : (
                        <div className="flex w-full min-w-0 items-center justify-between gap-2">
                            <span className={`block truncate ${displayValue ? '' : 'text-muted-foreground'}`}>{displayValue || placeholder}</span>
                            <div className="flex shrink-0 items-center space-x-1">
                                {value && value !== 'none' && (
                                    <button type="button" onClick={handleClear} className="text-muted-foreground hover:text-foreground">
                                        <X className="h-4 w-4" />
                                    </button>
                                )}
                                <ChevronDown
                                    className={`h-4 w-4 text-muted-foreground opacity-50 transition-transform ${isOpen ? 'rotate-180' : ''}`}
                                />
                            </div>
                        </div>
                    )}
                </div>

                {isOpen && (
                    <div className="absolute z-10 mt-1 w-full rounded-md border border-border bg-popover shadow-lg">
                        <div className="max-h-60 overflow-auto py-1">
                            {filteredOptions.length > 0 ? (
                                filteredOptions.map((option) => (
                                    <div
                                        key={option.value}
                                        className={`cursor-pointer px-3 py-2 text-sm select-none ${
                                            option.value === value
                                                ? 'bg-indigo-100 text-indigo-900 dark:bg-indigo-900 dark:text-indigo-100'
                                                : 'text-gray-900 hover:bg-gray-100 dark:text-gray-100 dark:hover:bg-gray-700'
                                        } `}
                                        onClick={() => handleSelect(option)}
                                    >
                                        {option.label}
                                    </div>
                                ))
                            ) : (
                                <div className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">No options found</div>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}
