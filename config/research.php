<?php

return [
    'admin_path' => env('BACADULU_ADMIN_PATH', 'panel-adminbaca-research'),
    'admin_email' => env('ADMIN_EMAIL'),
    'admin_password' => env('ADMIN_PASSWORD'),
    'profile_gate' => (int) env('RESEARCH_PROFILE_GATE', 80),
    'require_email_verification' => (bool) env('RESEARCH_REQUIRE_EMAIL_VERIFICATION', false),
    'ai_provider' => env('AI_PROVIDER', 'unconfigured'),
    'ai_model' => env('AI_MODEL', ''),
];
