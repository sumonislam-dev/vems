import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import toast from 'react-hot-toast';
import { initializeTheme } from './hooks/use-appearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

// Most forms only handle per-field validation errors (auto-populated by
// Inertia) and never add an onError/network-failure callback of their own.
// These two events are the ones that would otherwise fail completely
// silently — a genuinely broken response (500/419/503) or an unexpected
// exception (e.g. lost network mid-request) — so give the user *something*
// visible everywhere, without touching every individual form.
//
// preventDefault() suppresses Inertia's own default handling — a full-screen
// modal dumping the raw response body — which would otherwise render on top
// of (and hide) this toast. The real error is still in the server log /
// browser Network tab for debugging.
router.on('invalid', (event) => {
    event.preventDefault();
    toast.error('Something went wrong. Please try again.');
});

router.on('exception', (event) => {
    event.preventDefault();
    toast.error('Something went wrong. Please try again.');
    console.error(event.detail.exception);
});

// This will set light / dark mode on load...
initializeTheme();
