<?php

require dirname(__DIR__).'/vendor/autoload.php';

// Tests always use this dedicated SQLite file, without loading local credentials.
foreach ([
    'APP_ENV' => 'test',
    'APP_DEBUG' => '1',
    'KERNEL_CLASS' => App\Kernel::class,
    'APP_SECRET' => 'performcv-test-secret-not-for-production',
    'STRIPE_SECRET_KEY' => 'sk_test_performcv_fixture_not_a_real_key',
    'STRIPE_WEBHOOK_SECRET' => 'whsec_performcv_fixture_not_a_real_secret',
    'DATABASE_URL' => 'sqlite:///'.str_replace('\\', '/', dirname(__DIR__)).'/.runtime/performcv_suite_test.db',
] as $key => $value) {
    $_SERVER[$key] = $_ENV[$key] = $value;
}
