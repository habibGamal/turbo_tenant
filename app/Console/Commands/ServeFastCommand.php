<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand;

final class ServeFastCommand extends ServeCommand
{
    protected $name = 'serve:fast';

    protected $description = 'Serve locally with OPcache and immediate PHP source revalidation';

    protected function serverCommand()
    {
        $command = parent::serverCommand();
        $options = ['-d', 'opcache.enable_cli=1', '-d', 'opcache.validate_timestamps=1', '-d', 'opcache.revalidate_freq=0'];

        if (! extension_loaded('Zend OPcache')) {
            $library = mb_rtrim((string) ini_get('extension_dir'), '/\\').DIRECTORY_SEPARATOR
                .(PHP_OS_FAMILY === 'Windows' ? 'php_opcache.dll' : 'opcache.so');
            if (is_file($library)) {
                array_push($options, '-d', 'zend_extension='.$library);
            }
        }

        array_splice($command, 1, 0, $options);

        return $command;
    }
}
