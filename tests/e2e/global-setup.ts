import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const dbPath = path.join(root, 'database', 'e2e.sqlite');
const hotPath = path.join(root, 'public', 'hot');
const hotBackupPath = path.join(root, 'public', 'hot.e2e-bak');

export default async function globalSetup(): Promise<void> {
    if (fs.existsSync(hotPath)) {
        fs.renameSync(hotPath, hotBackupPath);
    }

    if (process.env.PLAYWRIGHT_SKIP_BUILD !== '1') {
        execSync('npm run build', { cwd: root, stdio: 'inherit' });
    }

    if (fs.existsSync(dbPath)) {
        fs.unlinkSync(dbPath);
    }

    fs.writeFileSync(dbPath, '');

    const env = {
        ...process.env,
        APP_ENV: 'testing',
        APP_URL: 'http://127.0.0.1:8010',
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: dbPath,
        DB_URL: '',
        SESSION_DRIVER: 'file',
        CACHE_STORE: 'array',
        QUEUE_CONNECTION: 'sync',
        MAIL_MAILER: 'array',
        PULSE_ENABLED: 'false',
        BCRYPT_ROUNDS: '4',
    };

    execSync('php artisan migrate:fresh --seed --force --no-interaction', {
        cwd: root,
        env,
        stdio: 'inherit',
    });

    execSync(
        'php artisan db:seed --class=Database\\Seeders\\E2eJourneySeeder --force --no-interaction',
        { cwd: root, env, stdio: 'inherit' },
    );
}
