import { closeSync, existsSync, mkdirSync, openSync } from 'node:fs';
import path from 'node:path';
import { execFileSync, spawn } from 'node:child_process';

export default async function globalSetup() {
  const projectRoot = path.resolve('.');
  const databaseDirectory = path.join(projectRoot, 'work');
  const database = path.join(databaseDirectory, 'playwright.sqlite');
  const php = path.join(projectRoot, '.tools', 'php', 'php.exe');

  mkdirSync(databaseDirectory, { recursive: true });
  if (!existsSync(database)) closeSync(openSync(database, 'w'));

  const environment = {
    ...process.env,
    APP_ENV: 'testing',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: database,
    SEED_DEMO_DATA: 'true',
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'array',
    QUEUE_CONNECTION: 'sync',
  };

  execFileSync(php, ['artisan', 'migrate:fresh', '--seed', '--force'], {
    cwd: projectRoot,
    stdio: 'inherit',
    env: environment,
  });

  const server = spawn(
    php,
    ['-S', '127.0.0.1:8001', '-t', 'public', 'tests/e2e/server.php'],
    { cwd: projectRoot, env: environment, stdio: 'ignore' },
  );

  let ready = false;
  for (let attempt = 0; attempt < 60; attempt++) {
    try {
      const response = await fetch('http://127.0.0.1:8001/login');
      if (response.ok) {
        ready = true;
        break;
      }
    } catch {
      // Retry until the direct PHP server is accepting requests.
    }
    await new Promise(resolve => setTimeout(resolve, 250));
  }
  if (!ready) throw new Error('Playwright Laravel server did not become ready.');

  return async () => {
    if (!server.killed) server.kill();
  };
}
