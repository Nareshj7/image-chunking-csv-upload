import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const port = Number(env.VITE_PORT ?? 5173);
    const host = env.VITE_HOST ?? '0.0.0.0';
    const hmrHost = env.VITE_HMR_HOST ?? 'localhost';

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
            tailwindcss(),
        ],
        server: {
            host,
            port,
            strictPort: true,
            cors: true,
            origin: `http://${hmrHost}:${port}`,
            hmr: {
                host: hmrHost,
            },
        },
    };
});
