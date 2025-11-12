<?php

declare(strict_types=1);

namespace App\Console\Commands;

use DB;
use Illuminate\Console\Command;

final class DatabaseFresh extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:database-fresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Ensure tenancy is ended before starting
        $this->call('migrate:fresh', [
            '--seed' => true,
        ]);
        DB::statement('DROP DATABASE IF EXISTS tenantclinic2');
        $this->info('Database fresh and tenantclinic2 dropped successfully.');
        $this->call('app:create-tenant');
        $this->info('Tenant clinic2 created successfully.');
    }
}
