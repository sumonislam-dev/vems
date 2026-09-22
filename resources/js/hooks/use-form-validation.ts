import { useState, useCallback, useEffect } from 'react';

/**
 * Custom hook for form validation that works with Inertia.js forms
 * 
 * Provides client-side validation without requiring additional dependencies
 * Integrates seamlessly with the base form components
 * 
 * @example
 * const { data, setData, post, processing, errors } = useForm({
 *   name: '',
 *   email: ''
 * });
 * 
 * const { validate, clearErrors, hasErrors } = useFormValidation({
 *   name: [
 *     (value) => !value ? 'Name is required' : undefined,
 *     (value) => value.length < 2 ? 'Name must be at least 2 characters' : undefined
 *   ],
 *   email: [
 *     (value) => !value ? 'Email is required' : undefined,
 *     (value) => !/\S+@\S+\.\S+/.test(value) ? 'Invalid email' : undefined
 *   ]
 * });
 * 
 * const handleSubmit = (e) => {
 *   e.preventDefault();
 *   const validationErrors = validate(data);
 *   if (hasErrors(validationErrors)) {
 *     return; // Don't submit if there are validation errors
 *   }
 *   post(route('users.store'));
 * };
 */

export type ValidationRule<T = unknown> = (value: T) => string | undefined;
export type ValidationRules<T = Record<string, unknown>> = {
  [K in keyof T]?: ValidationRule<T[K]>[];
};
export type ValidationErrors<T = Record<string, unknown>> = {
  [K in keyof T]?: string;
};

interface UseFormValidationReturn<T = Record<string, unknown>> {
  errors: ValidationErrors<T>;
  validate: (data: T) => ValidationErrors<T>;
  validateField: (field: keyof T, value: T[keyof T]) => string | undefined;
  clearErrors: (fields?: (keyof T)[]) => void;
  clearError: (field: keyof T) => void;
  hasErrors: (errors?: ValidationErrors<T>) => boolean;
  setError: (field: keyof T, error: string) => void;
  setErrors: (errors: ValidationErrors<T>) => void;
}

export function useFormValidation<T = Record<string, unknown>>(
  rules: ValidationRules<T>
): UseFormValidationReturn<T> {
  const [errors, setErrorsState] = useState<ValidationErrors<T>>({});

  const validateField = useCallback((field: keyof T, value: T[keyof T]): string | undefined => {
    const fieldRules = rules[field];
    if (!fieldRules) return undefined;

    for (const rule of fieldRules) {
      const error = rule(value);
      if (error) return error;
    }
    return undefined;
  }, [rules]);

  const validate = useCallback((data: T): ValidationErrors<T> => {
    const newErrors: ValidationErrors<T> = {};

    Object.keys(rules).forEach((field) => {
      const fieldKey = field as keyof T;
      const error = validateField(fieldKey, data[fieldKey]);
      if (error) {
        newErrors[fieldKey] = error;
      }
    });

    setErrorsState(newErrors);
    return newErrors;
  }, [rules, validateField]);

  const clearErrors = useCallback((fields?: (keyof T)[]) => {
    if (fields) {
      setErrorsState(prev => {
        const newErrors = { ...prev };
        fields.forEach(field => {
          delete newErrors[field];
        });
        return newErrors;
      });
    } else {
      setErrorsState({});
    }
  }, []);

  const clearError = useCallback((field: keyof T) => {
    setErrorsState(prev => {
      const newErrors = { ...prev };
      delete newErrors[field];
      return newErrors;
    });
  }, []);

  const hasErrors = useCallback((errorsToCheck?: ValidationErrors<T>): boolean => {
    const errorsObj = errorsToCheck || errors;
    return Object.keys(errorsObj).length > 0;
  }, [errors]);

  const setError = useCallback((field: keyof T, error: string) => {
    setErrorsState(prev => ({
      ...prev,
      [field]: error
    }));
  }, []);

  const setErrors = useCallback((newErrors: ValidationErrors<T>) => {
    setErrorsState(newErrors);
  }, []);

  return {
    errors,
    validate,
    validateField,
    clearErrors,
    clearError,
    hasErrors,
    setError,
    setErrors
  };
}

// Common validation rules
export const commonValidationRules = {
  required: <T>(message = "This field is required"): ValidationRule<T> => 
    (value) => {
      if (value === null || value === undefined) return message;
      if (typeof value === 'string' && value.trim() === '') return message;
      if (Array.isArray(value) && value.length === 0) return message;
      return undefined;
    },

  email: (message = "Please enter a valid email address"): ValidationRule<string> => 
    (value) => {
      if (!value) return undefined;
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return !emailRegex.test(value) ? message : undefined;
    },

  minLength: (min: number, message?: string): ValidationRule<string> => 
    (value) => {
      if (!value) return undefined;
      const msg = message || `Must be at least ${min} characters`;
      return value.length < min ? msg : undefined;
    },

  maxLength: (max: number, message?: string): ValidationRule<string> => 
    (value) => {
      if (!value) return undefined;
      const msg = message || `Must be no more than ${max} characters`;
      return value.length > max ? msg : undefined;
    },

  pattern: (regex: RegExp, message = "Invalid format"): ValidationRule<string> => 
    (value) => {
      if (!value) return undefined;
      return !regex.test(value) ? message : undefined;
    },

  min: (min: number, message?: string): ValidationRule<number> => 
    (value) => {
      if (value === null || value === undefined) return undefined;
      const msg = message || `Must be at least ${min}`;
      return value < min ? msg : undefined;
    },

  max: (max: number, message?: string): ValidationRule<number> => 
    (value) => {
      if (value === null || value === undefined) return undefined;
      const msg = message || `Must be no more than ${max}`;
      return value > max ? msg : undefined;
    },

  url: (message = "Please enter a valid URL"): ValidationRule<string> => 
    (value) => {
      if (!value) return undefined;
      try {
        new URL(value);
        return undefined;
      } catch {
        return message;
      }
    },

  phone: (message = "Please enter a valid phone number"): ValidationRule<string> =>
    (value) => {
      if (!value) return undefined;
      const phoneRegex = /^[+]?[1-9][\d]{0,15}$/;
      return !phoneRegex.test(value.replace(/[\s\-()]/g, '')) ? message : undefined;
    },

  confirmPassword: (originalPassword: string, message = "Passwords do not match"): ValidationRule<string> => 
    (value) => {
      if (!value) return undefined;
      return value !== originalPassword ? message : undefined;
    },

  oneOf: <T>(options: T[], message?: string): ValidationRule<T> => 
    (value) => {
      if (value === null || value === undefined) return undefined;
      const msg = message || `Must be one of: ${options.join(', ')}`;
      return !options.includes(value) ? msg : undefined;
    }
};

// Hook for real-time validation as user types
export function useRealtimeValidation<T extends Record<string, unknown>>(
  rules: ValidationRules<T>,
  data: T,
  debounceMs = 300
): ValidationErrors<T> {
  const [errors, setErrors] = useState<ValidationErrors<T>>({});
  const { validateField } = useFormValidation(rules);

  // Debounce validation to avoid excessive re-renders
  const debouncedValidate = useCallback(
    debounce((field: keyof T, value: T[keyof T]) => {
      const error = validateField(field, value);
      setErrors(prev => ({
        ...prev,
        [field]: error
      }));
    }, debounceMs),
    [validateField, debounceMs]
  );

  // Validate fields when data changes
  useEffect(() => {
    Object.keys(data).forEach(field => {
      const fieldKey = field as keyof T;
      debouncedValidate(fieldKey, data[fieldKey]);
    });
  }, [data, debouncedValidate]);

  return errors;
}

// Simple debounce utility
// eslint-disable-next-line @typescript-eslint/no-explicit-any
function debounce<T extends (...args: any[]) => any>(
  func: T,
  wait: number
): (...args: Parameters<T>) => void {
  let timeout: NodeJS.Timeout;
  return (...args: Parameters<T>) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => func(...args), wait);
  };
}
