<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Sur certains environnements (Git Bash / Windows), le APP_ENV de
        // phpunit.xml n'est pas appliqué : on force l'environnement de test
        // pour que les exemptions de tests (CSRF notamment) s'activent.
        app()->instance('env', 'testing');
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
