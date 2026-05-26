<?php

namespace NathanHeffley\LaravelWatermelon\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use NathanHeffley\LaravelWatermelon\Tests\Fixtures\TestLeadMmsWatermelonService;
use NathanHeffley\LaravelWatermelon\Tests\models\Task;
use NathanHeffley\LaravelWatermelon\Tests\TestCase;

class AlwaysPullFullTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNowAndTimezone('2025-08-15 12:00:00', 'UTC');

        Config::set('watermelon.models', [
            'tasks' => Task::class,
        ]);

        Config::set('watermelon.resolveStartDateSync', TestLeadMmsWatermelonService::class);
        Config::set('watermelon.resolveMaxDateSync', TestLeadMmsWatermelonService::class);
    }

    /** @test */
    public function reference_row_older_than_tenant_sync_window_is_excluded_without_always_pull_full(): void
    {
        Config::set('watermelon.always_pull_full', []);

        Task::query()->create([
            'watermelon_id' => 'oldstatus',
            'content' => 'Lookup old',
            'is_completed' => false,
            'created_at' => '2024-01-15 10:00:00',
            'updated_at' => '2024-01-15 10:00:00',
        ]);

        Task::query()->create([
            'watermelon_id' => 'inthewindow',
            'content' => 'In window',
            'is_completed' => true,
            'created_at' => '2025-07-01 10:00:00',
            'updated_at' => '2025-07-01 10:00:00',
        ]);

        $response = $this->json('GET', '/sync?last_pulled_at=null&first_sync=true&schema_version=1');
        $response->assertStatus(200);

        $response->assertJsonPath('changes.tasks.created', [
            [
                'id' => 'inthewindow',
                'content' => 'In window',
                'is_completed' => true,
            ],
        ]);
    }

    /** @test */
    public function reference_row_older_than_tenant_sync_window_is_included_with_always_pull_full(): void
    {
        Config::set('watermelon.always_pull_full', ['tasks']);

        Task::query()->create([
            'watermelon_id' => 'oldstatus',
            'content' => 'Lookup old',
            'is_completed' => false,
            'created_at' => '2024-01-15 10:00:00',
            'updated_at' => '2024-01-15 10:00:00',
        ]);

        Task::query()->create([
            'watermelon_id' => 'inthewindow',
            'content' => 'In window',
            'is_completed' => true,
            'created_at' => '2025-07-01 10:00:00',
            'updated_at' => '2025-07-01 10:00:00',
        ]);

        $response = $this->json('GET', '/sync?last_pulled_at=null&first_sync=true&schema_version=1');
        $response->assertStatus(200);

        $created = collect($response->json('changes.tasks.created'))->sortBy('id')->values()->all();

        $this->assertCount(2, $created);
        $this->assertEquals([
            [
                'id' => 'inthewindow',
                'content' => 'In window',
                'is_completed' => true,
            ],
            [
                'id' => 'oldstatus',
                'content' => 'Lookup old',
                'is_completed' => false,
            ],
        ], $created);
    }

    /** @test */
    public function always_pull_full_returns_empty_created_on_first_sync_continuation_batch(): void
    {
        Config::set('watermelon.always_pull_full', ['tasks']);

        Task::query()->create([
            'watermelon_id' => 'oldstatus',
            'content' => 'Lookup old',
            'is_completed' => false,
            'created_at' => '2024-01-15 10:00:00',
            'updated_at' => '2024-01-15 10:00:00',
        ]);

        $t1 = Carbon::parse('2025-09-01 00:00:00')->timestamp;

        $response = $this->json('GET', '/sync?last_pulled_at='.$t1.'&first_sync=true&schema_version=1');
        $response->assertStatus(200);

        $response->assertJsonPath('changes.tasks.created', []);
    }

    /** @test */
    public function incremental_pull_puts_all_active_always_pull_full_rows_in_updated(): void
    {
        Config::set('watermelon.always_pull_full', ['tasks']);

        Task::query()->create([
            'watermelon_id' => 'refa',
            'content' => 'A',
            'is_completed' => false,
            'created_at' => '2024-01-15 10:00:00',
            'updated_at' => '2024-01-15 10:00:00',
        ]);

        Task::query()->create([
            'watermelon_id' => 'refb',
            'content' => 'B',
            'is_completed' => true,
            'created_at' => '2024-06-15 10:00:00',
            'updated_at' => '2024-06-15 10:00:00',
        ]);

        $lastPulledAt = Carbon::parse('2026-01-01 00:00:00')->timestamp;

        $response = $this->json('GET', '/sync?last_pulled_at='.$lastPulledAt.'&schema_version=1');
        $response->assertStatus(200);

        $updated = collect($response->json('changes.tasks.updated'))->sortBy('id')->values()->all();

        $this->assertCount(2, $updated);
        $response->assertJsonPath('changes.tasks.created', []);
        $this->assertEquals([
            [
                'id' => 'refa',
                'content' => 'A',
                'is_completed' => false,
            ],
            [
                'id' => 'refb',
                'content' => 'B',
                'is_completed' => true,
            ],
        ], $updated);
    }
}
