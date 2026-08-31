<?php

namespace Tests;

/**
 * Boots the app with the analytics dashboard switched on.
 *
 * The route is registered during provider boot, so the setting has to be in
 * the environment before the application is created — it cannot be flipped
 * from inside a test.
 */
abstract class DashboardTestCase extends TestCase
{
    public function createApplication()
    {
        putenv('AD_CONVERSIONS_DASHBOARD_ENABLED=true');
        $_ENV['AD_CONVERSIONS_DASHBOARD_ENABLED'] = 'true';
        $_SERVER['AD_CONVERSIONS_DASHBOARD_ENABLED'] = 'true';

        return parent::createApplication();
    }

    protected function tearDown(): void
    {
        putenv('AD_CONVERSIONS_DASHBOARD_ENABLED');
        unset($_ENV['AD_CONVERSIONS_DASHBOARD_ENABLED'], $_SERVER['AD_CONVERSIONS_DASHBOARD_ENABLED']);

        parent::tearDown();
    }
}
