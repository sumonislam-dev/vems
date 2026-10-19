import '../css/app.css';

import { createInertiaApp, router, type ResolvedComponent } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import toast from 'react-hot-toast';
import { initializeTheme } from './hooks/use-appearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// A page's code is loaded the first time it is opened. If that download fails
// (the Vite dev server restarted or re-bundled, or a new build replaced the
// old files), Inertia would finish the progress bar and leave you on the old
// page. Load the target page with a full browser request instead, once per
// URL so a page that is really broken doesn't reload forever.
const RELOAD_KEY = 'vems:reloaded-for';

function reloadForPage(url: string | undefined, error: unknown): Promise<never> {
    const target = url ?? window.location.href;
    try {
        if (sessionStorage.getItem(RELOAD_KEY) !== target) {
            sessionStorage.setItem(RELOAD_KEY, target);
            window.location.href = target;
            return new Promise(() => {});
        }
    } catch {
        // sessionStorage unavailable: fall through to the error
    }
    throw error;
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name, page) =>
        resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob<{ default: ResolvedComponent }>('./pages/**/*.tsx'))
            .then((module) => module.default)
            .catch((error) => reloadForPage(page?.url, error)),
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
router.on('httpException', (event) => {
    event.preventDefault();
    const status = event.detail.response.status;

    // Session expired or logged out elsewhere: a fresh load gets a new
    // session/CSRF token, or the login page
    if (status === 401 || status === 419) {
        toast.error('Your session has expired. Reloading...');
        window.location.reload();
        return;
    }

    toast.error(httpErrorMessage(status));
});

router.on('networkError', (event) => {
    event.preventDefault();
    toast.error('Something went wrong. Please try again.');
    console.error(event.detail.error);
});

function httpErrorMessage(status: number): string {
    if (status === 403) return "You don't have permission to do that.";
    if (status === 404) return 'That page or record was not found.';
    if (status === 429) return 'Too many requests. Please wait a moment and try again.';
    if (status === 503) return 'The system is under maintenance. Please try again shortly.';
    if (status >= 500) return 'Server error. Please try again, or contact support if it keeps happening.';
    return 'Something went wrong. Please try again.';
}

// A page loaded fine, so a later failure may reload again
router.on('navigate', () => {
    try {
        sessionStorage.removeItem(RELOAD_KEY);
    } catch {
        // ignore
    }
});

// This will set light / dark mode on load...
initializeTheme();
