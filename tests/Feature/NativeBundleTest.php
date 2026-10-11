<?php

declare(strict_types=1);

it('keeps personal data and agent config out of the Android bundle', function (string $path) {
    expect(config('nativephp.cleanup_exclude_files'))->toContain($path);
})->with(['docs', '.claude', '.mcp.json', 'database/database.sqlite']);

it('strips secrets and local build settings from the bundled .env', function (string $key) {
    expect(config('nativephp.cleanup_env_keys'))->toContain($key);
})->with(['TMDB_TOKEN', '*_PASSWORD', 'ANDROID_KEYSTORE_FILE', 'ANDROID_KEY_ALIAS', 'NATIVEPHP_ANDROID_SDK_LOCATION']);
