import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { Suspense } from 'react';
import ReactDOMServer from 'react-dom/server';

import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import AuthLayout from '@/layouts/auth-layout';
import TrackingLayout from '@/layouts/tracking-layout';

const appName = import.meta.env.VITE_APP_NAME || 'PBM Agency';

const INLINE_IMAGE_PRELOAD =
    /<link rel="preload"(?: [a-zA-Z-]+="[^"]*")*? as="image"[^>]*\/?>/g;

const renderWithoutInlinePreloads = (
    element: Parameters<typeof ReactDOMServer.renderToString>[0],
): string =>
    ReactDOMServer.renderToString(element).replace(INLINE_IMAGE_PRELOAD, '');

createServer((page) =>
    createInertiaApp({
        page,
        render: renderWithoutInlinePreloads,
        title: (title) => (title ? `${title}` : appName),
        resolve: (name) =>
            resolvePageComponent(
                `./pages/${name}.tsx`,
                import.meta.glob('./pages/**/*.tsx'),
            ) as any,
        layout: (name) => {
            switch (true) {
                case name.startsWith('auth/'):
                    return [TrackingLayout, AuthLayout];
                default:
                    return TrackingLayout;
            }
        },
        setup: ({ App, props }) => {
            return (
                // Mirrors app.tsx: the client wraps the page in <Suspense>, so
                // the server must emit the same boundary or hydration fails.
                <TooltipProvider delayDuration={0}>
                    <Suspense fallback={null}>
                        <App {...props} />
                    </Suspense>
                    <Toaster />
                </TooltipProvider>
            );
        },
    }),
);
