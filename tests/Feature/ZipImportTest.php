<?php

use App\Models\Show;
use App\Models\User;
use App\Models\WatchedEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function recordsCsv(): string
{
    return "key,s_id,series_name,season_number,episode_number,is_archived,is_for_later,followed_at,updated_at\n"
        ."user-series-1,1234,Test Show,,,false,false,,\n"
        ."watch-episode-1,1234,Test Show,1,1,,,,2026-01-01\n";
}

function exportZip(array $files): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'tvt').'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();

    $bytes = (string) file_get_contents($path);
    unlink($path);

    return UploadedFile::fake()->createWithContent('export.zip', $bytes);
}

it('imports shows and watches from a tv time zip', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::settings.import')
        ->set('archive', exportZip(['tracking-prod-records-v2.csv' => recordsCsv()]))
        ->call('import')
        ->assertHasNoErrors();

    $show = Show::where('tvdb_id', 1234)->first();
    expect($show)->not->toBeNull()
        ->and($show->name)->toBe('Test Show')
        ->and(WatchedEpisode::where('user_id', $user->id)->count())->toBe(1);
});

it('runs the tmdb sync after import when a token is configured', function () {
    config(['services.tmdb.token' => 'fake-token']);
    Http::fake(['*' => Http::response([], 200)]);

    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::settings.import')
        ->set('archive', exportZip(['tracking-prod-records-v2.csv' => recordsCsv()]))
        ->call('import')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'themoviedb.org'));
});

it('rejects a zip without the records file', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::settings.import')
        ->set('archive', exportZip(['random.csv' => "a,b\n1,2\n"]))
        ->call('import')
        ->assertHasErrors('archive');

    expect(Show::count())->toBe(0);
});
