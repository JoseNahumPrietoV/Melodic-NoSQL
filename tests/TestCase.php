<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        $app['config']->set('database.connections.mongodb.dsn', env('MONGODB_TEST_URI')
            ?? throw new RuntimeException('Define MONGODB_TEST_URI en .env para correr las pruebas (ver docs/manual.md).'));
        $app['config']->set('database.connections.mongodb.database', 'melodic_testing');

        return $app;
    }
}
