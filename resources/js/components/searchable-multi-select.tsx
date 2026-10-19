import { Check, ChevronDown, Search, X } from 'lucide-react';
import React, { useEffect, useMemo, useRef, useState } from 'react';

interface Option {
    label: string;
    value: string;
}

interface SearchableMultiSelectProps {
    label: string;
    name: string;
    value: (string | number)[];
    onChange: (value: string[]) => void;
    options: Option[];
    error?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    /** Keep the label for screen readers only, e.g. when a card title already names the field */
    hideLabel?: boolean;
    /** Text for the closed box instead of the chosen names, e.g. "12 passengers selected" */
    selectedText?: (count: number) => string;
}

/**
 * Multi-select twin of SearchableSelect: the same type-to-search box, but the
 * list stays open and each click toggles an option.
 */
export function SearchableMultiSelect({
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
    selectedText,
}: SearchableMultiSelectProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const dropdownRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    const selected = useMemo(() => value.map(String), [value]);
    const isSelected = (option: Option) => selected.includes(String(option.value));

    const filteredOptions = useMemo(
        () => (searchTerm ? options.filter((option) => option.label.toLowerCase().includes(searchTerm.toLowerCase())) : options),
        [searchTerm, options],
    );

    const displayValue =
        selectedText && selected.length > 0
            ? selectedText(selected.length)
            : options
                  .filter(isSelected)
                  .map((option) => option.label)
                  .join(', ');

    // Close dropdown when clicking outside
    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setIsOpen(false);
                setSearchTerm('');
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const handleToggle = () => {
        if (disabled) return;
        setIsOpen(!isOpen);
        if (!isOpen) {
            setTimeout(() => inputRef.current?.focus(), 100);
        } else {
            setSearchTerm('');
        }
    };

    const handleSelect = (option: Option) => {
        const optionValue = String(option.value);
        onChange(selected.includes(optionValue) ? selected.filter((v) => v !== optionValue) : [...selected, optionValue]);
        inputRef.current?.focus();
    };

    const handleClear = (e: React.MouseEvent) => {
        e.stopPropagation();
        onChange([]);
        setSearchTerm('');
    };

    return (
        <div className="space-y-2">
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
                                {selected.length > 0 && (
                                    <button
                                        type="button"
                                        onClick={handleClear}
                                        aria-label={`Clear ${label}`}
                                        className="text-muted-foreground hover:text-foreground"
                                    >
                                        <X className="h-4 w-4" />
                                    </button>
                                )}
                                <ChevronDown className="h-4 w-4 text-muted-foreground opacity-50" />
                            </div>
                        </div>
                    )}
                </div>

                {isOpen && (
                    <div className="absolute z-10 mt-1 w-full rounded-md border border-border bg-popover shadow-lg">
                        <div className="max-h-60 overflow-auto py-1" role="listbox" aria-multiselectable="true">
                            {filteredOptions.length > 0 ? (
                                filteredOptions.map((option) => (
                                    <div
                                        key={option.value}
                                        role="option"
                                        aria-selected={isSelected(option)}
                                        className={`flex cursor-pointer items-center gap-2 px-3 py-2 text-sm select-none ${
                                            isSelected(option)
                                                ? 'bg-indigo-100 text-indigo-900 dark:bg-indigo-900 dark:text-indigo-100'
                                                : 'text-gray-900 hover:bg-gray-100 dark:text-gray-100 dark:hover:bg-gray-700'
                                        }`}
                                        onClick={() => handleSelect(option)}
                                    >
                                        <span className="flex h-4 w-4 shrink-0 items-center justify-center">
                                            {isSelected(option) && <Check className="h-3.5 w-3.5" />}
                                        </span>
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
