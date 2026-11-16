<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('finance:about', function () {
    $this->info('Student Finance Manager — Laravel 13 / PostgreSQL migration branch.');
})->purpose('Display migration branch information');
