<?php

declare(strict_types=1);

use App\Console\Commands\ServeFastCommand;
use Symfony\Component\Console\Input\ArrayInput;

it('keeps source changes visible when starting the opcode-cached development server', function (): void {
    $command = app(ServeFastCommand::class);
    $command->setLaravel(app());
    (new ReflectionProperty($command, 'input'))->setValue($command, new ArrayInput([
        '--host' => '127.0.0.1', '--port' => 8000,
    ], $command->getDefinition()));
    $arguments = (new ReflectionMethod($command, 'serverCommand'))->invoke($command);
    expect($arguments)->toContain('opcache.enable_cli=1', 'opcache.validate_timestamps=1', 'opcache.revalidate_freq=0')
        ->and(array_slice($arguments, -3))->toBe(['-S', '127.0.0.1:8000', base_path('server.php')]);
});
