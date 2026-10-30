<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Запобіжник: RefreshDatabase стирає базу, тож тести йдуть лише в logistics_test.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $database = $app['config']->get('database.connections.'.$app['config']->get('database.default').'.database');

        if ($database !== 'logistics_test') {
            throw new RuntimeException("Tests must run against logistics_test, got [{$database}]. Check phpunit.xml.");
        }

        return $app;
    }
}
