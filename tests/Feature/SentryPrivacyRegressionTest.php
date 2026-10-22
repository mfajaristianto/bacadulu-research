<?php

namespace Tests\Feature;

use App\Http\Middleware\SentryRequestContext;
use App\Services\Monitoring\SentryContext;
use Tests\TestCase;

class SentryPrivacyRegressionTest extends TestCase
{
    public function test_sentry_is_configured_for_error_tracking_only(): void
    {
        $this->assertSame(
            1.0,
            (float)
                config(
                    'sentry.sample_rate'
                )
        );

        $this->assertSame(
            0.0,
            (float)
                config(
                    'sentry.traces_sample_rate'
                )
        );

        $this->assertSame(
            0.0,
            (float)
                config(
                    'sentry.profiles_sample_rate'
                )
        );
    }

    public function test_sentry_does_not_send_default_pii_or_request_body(): void
    {
        $this->assertFalse(
            (bool)
                config(
                    'sentry.send_default_pii'
                )
        );

        $this->assertSame(
            'never',
            config(
                'sentry.max_request_body_size'
            )
        );
    }

    public function test_sentry_logs_and_sensitive_breadcrumbs_are_disabled(): void
    {
        $this->assertFalse(
            (bool)
                config(
                    'sentry.enable_logs'
                )
        );

        $this->assertFalse(
            (bool)
                config(
                    'sentry.breadcrumbs.sql_queries'
                )
        );

        $this->assertFalse(
            (bool)
                config(
                    'sentry.breadcrumbs.sql_bindings'
                )
        );

        $this->assertFalse(
            (bool)
                config(
                    'sentry.breadcrumbs.http_client_requests'
                )
        );

        $this->assertTrue(
            (bool)
                config(
                    'sentry.breadcrumbs.queue_info'
                )
        );
    }

    public function test_sentry_context_services_can_be_resolved(): void
    {
        $this->assertInstanceOf(
            SentryContext::class,
            app(
                SentryContext::class
            )
        );

        $this->assertInstanceOf(
            SentryRequestContext::class,
            app(
                SentryRequestContext::class
            )
        );
    }
}