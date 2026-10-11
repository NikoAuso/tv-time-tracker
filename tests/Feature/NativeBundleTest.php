<?php

declare(strict_types=1);

it('keeps personal data and agent config out of the Android bundle', function (string $path) {
    expect(config('nativephp.cleanup_exclude_files'))->toContain($path);
})->with(['docs', '.claude', '.mcp.json', 'database/database.sqlite']);
