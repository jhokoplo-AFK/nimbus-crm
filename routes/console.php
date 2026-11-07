<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('crm:demo', function () {
    $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);

    $this->info('Nimbus CRM demo data is ready.');
    $this->line('  admin@nimbus.test / password   (Admin)');
    $this->line('  manager@nimbus.test / password (Sales Manager)');
    $this->line('  rep@nimbus.test / password     (Sales Rep)');
})->purpose('Rebuild the Nimbus CRM demo dataset');
