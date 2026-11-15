import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

/** Resolve the token from the local E2E tenant without exposing it in command arguments or logs. */
export function integrationTokenFor(baseURL: string | undefined): string {
  if (process.env.E2E_INTEGRATION_TOKEN) return process.env.E2E_INTEGRATION_TOKEN;

  const target = new URL(baseURL ?? 'http://e2e.localhost:8000');
  if (target.protocol !== 'http:' || target.hostname !== 'e2e.localhost') {
    throw new Error('Set E2E_INTEGRATION_TOKEN when testing a non-local E2E tenant.');
  }

  // Only the dedicated local tenant may have a token generated automatically.
  const php = `
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
    if (! $app->environment('local', 'testing')) { exit(2); }
    $tenant = App\\Models\\Tenant::find('e2e');
    if (! $tenant || ! $tenant->domains()->where('domain', 'e2e.localhost')->exists()) { exit(3); }
    $token = $tenant->run(function () {
      $service = app(App\\Plugins\\OrderManagement\\src\\Services\\IntegrationTokenService::class);
      return $service->get() ?? $service->generate();
    });
    if (! is_string($token) || $token === '') { exit(4); }
    echo $token;
  `;

  try {
    const token = execFileSync('php', ['-r', php], {
      cwd: fileURLToPath(new URL('../../../', import.meta.url)),
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'pipe'],
    }).trim();
    if (!token) throw new Error('Empty token');
    return token;
  } catch {
    throw new Error('Could not obtain the local e2e tenant integration token. Provision and seed the e2e tenant first.');
  }
}
