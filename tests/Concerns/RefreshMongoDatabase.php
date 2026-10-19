<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

trait RefreshMongoDatabase
{
    protected function setUpRefreshMongoDatabase(): void
    {
        $database = DB::connection('mongodb')->getDatabase();

        foreach ($database->listCollectionNames() as $collection) {
            $database->dropCollection($collection);
        }

        $this->artisan('migrate', ['--path' => 'database/migrations/mongodb']);
    }
}
