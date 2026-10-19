import React, { useState, useRef, useEffect } from 'react';
import { ChevronDown, Search, X } from 'lucide-react';

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
}

export function SearchableSelect({
    label,
    name,
    value,
    onChange,
    options,
    error,
    placeholder = "Search and select...",
    required = false,
    disabled = false,
}: SearchableSelectProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [filteredOptions, setFilteredOptions] = useState(options);
    const dropdownRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    // Get the display value
    const selectedOption = options.find(option => String(option.value) === String(value));
    const displayValue = selectedOption ? selectedOption.label : '';

    // Filter options based on search term
    useEffect(() => {
        if (searchTerm) {
            const filtered = options.filter(option =>
                option.label.toLowerCase().includes(searchTerm.toLowerCase())
            );
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
            <span
                id={`${name}-label`}
                className="block text-sm font-medium text-gray-700 dark:text-gray-300"
            >
                {label}
                {required && <span className="text-red-500 ml-1">*</span>}
            </span>

            <div className="relative" ref={dropdownRef}>
                <div
                    className={`
                        relative w-full cursor-pointer rounded-md border border-input bg-background px-3 py-2 text-left shadow-sm
                        ${error
                            ? 'border-red-300 focus-within:border-red-500 focus-within:ring-red-500'
                            : 'border-input focus-within:border-indigo-500 focus-within:ring-indigo-500'
                        }
                        ${disabled ? 'cursor-not-allowed bg-muted/70' : 'hover:border-border'}
                        focus-within:ring-1 transition-colors
                    `}
                    onClick={handleToggle}
                    role="combobox"
                    aria-labelledby={`${name}-label`}
                    aria-expanded={isOpen}
                    aria-invalid={!!error}
                    aria-disabled={disabled}
                >
                    {isOpen ? (
                        <div className="flex items-center">
                            <Search className="h-4 w-4 text-gray-400 dark:text-gray-500 mr-2" />
                            <input
                                ref={inputRef}
                                type="text"
                                className="flex-1 border-none bg-transparent p-0 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-0"
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
                        <div className="flex items-center justify-between">
                            <span className={`block truncate ${displayValue ? 'text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400'}`}>
                                {displayValue || placeholder}
                            </span>
                            <div className="flex items-center space-x-1">
                                {value && value !== 'none' && (
                                    <button
                                        type="button"
                                        onClick={handleClear}
                                        className="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                    >
                                        <X className="h-4 w-4" />
                                    </button>
                                )}
                                <ChevronDown className={`h-4 w-4 text-gray-400 dark:text-gray-500 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
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
                                        className={`
                                            cursor-pointer select-none px-3 py-2 text-sm
                                            ${option.value === value
                                                ? 'bg-indigo-100 dark:bg-indigo-900 text-indigo-900 dark:text-indigo-100'
                                                : 'text-gray-900 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700'
                                            }
                                        `}
                                        onClick={() => handleSelect(option)}
                                    >
                                        {option.label}
                                    </div>
                                ))
                            ) : (
                                <div className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                    No options found
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {error && (
                <p className="text-sm text-red-600 dark:text-red-400">{error}</p>
            )}
        </div>
    );
}
