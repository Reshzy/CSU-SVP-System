import fs from 'node:fs';
import path from 'node:path';

export default async function globalTeardown(): Promise<void> {
    const hotPath = path.join(process.cwd(), 'public', 'hot');
    const hotBackupPath = path.join(process.cwd(), 'public', 'hot.e2e-bak');

    if (fs.existsSync(hotBackupPath)) {
        if (fs.existsSync(hotPath)) {
            fs.unlinkSync(hotPath);
        }

        fs.renameSync(hotBackupPath, hotPath);
    }
}
