import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    timeout: 120_000,
    expect: { timeout: 20_000 },
    retries: 0,
    reporter: 'list',
    use: {
        baseURL: 'http://127.0.0.1:8010',
        trace: 'retain-on-failure',
    },
    globalSetup: './tests/e2e/global-setup.ts',
    globalTeardown: './tests/e2e/global-teardown.ts',
    webServer: {
        command: 'php artisan serve --env=testing --host=127.0.0.1 --port=8010',
        url: 'http://127.0.0.1:8010',
        reuseExistingServer: false,
        timeout: 120_000,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
