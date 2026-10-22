<?php

namespace Tests\Feature;

use App\Http\Controllers\ResearchSubmissionController;
use App\Models\User;
use App\Services\Research\ResearchSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FinalWiringRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_research_run_route_uses_queue_submission_controller(): void
    {
        $route =
            Route::getRoutes()
                ->getByName(
                    'research.run'
                );

        $this->assertNotNull(
            $route
        );

        $this->assertSame(
            ResearchSubmissionController::class
            .'@store',
            $route
                ->getActionName()
        );
    }

    public function test_effective_settings_are_nested_for_admin_form_and_flat_for_audit(): void
    {
        $user =
            User::factory()
                ->create();

        $settings =
            app(
                ResearchSettingsService::class
            );

        $settings->persist(
            [
                'retrieval.minimum_evidence' =>
                    3,

                'queue.live_refresh_seconds' =>
                    4,

                'documents.default_type' =>
                    'Research Report',
            ],
            $user->id
        );

        $nested =
            $settings
                ->effective();

        $flat =
            $settings
                ->effectiveFlat();

        $this->assertSame(
            3,
            data_get(
                $nested,
                'retrieval.minimum_evidence'
            )
        );

        $this->assertSame(
            4,
            data_get(
                $nested,
                'queue.live_refresh_seconds'
            )
        );

        $this->assertSame(
            'Research Report',
            data_get(
                $nested,
                'documents.default_type'
            )
        );

        $this->assertSame(
            3,
            $flat[
                'retrieval.minimum_evidence'
            ]
        );

        $this->assertSame(
            4,
            $flat[
                'queue.live_refresh_seconds'
            ]
        );
    }

    public function test_sentry_privacy_defaults_do_not_send_default_pii_or_request_body(): void
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
}