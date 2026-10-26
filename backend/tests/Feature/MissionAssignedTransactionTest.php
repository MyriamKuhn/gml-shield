<?php

namespace Tests\Feature;

use App\Events\MissionAssigned;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MissionAssignedTransactionTest extends TestCase
{
    public function test_rolled_back_transaction_does_not_dispatch_event(): void
    {
        $received = [];

        Event::listen(
            MissionAssigned::class,
            function (MissionAssigned $event) use (&$received): void {
                $received[] = $event->missionId;
            }
        );

        DB::beginTransaction();

        try {
            MissionAssigned::dispatch(
                missionId: 42,
                driverId: 7,
                title: 'Mission annulée',
                reference: 'GML-ROLLBACK-001',
            );

            $this->assertSame([], $received);
        } finally {
            DB::rollBack();
        }

        $this->assertSame([], $received);
    }

    public function test_committed_transaction_dispatches_event(): void
    {
        $received = [];

        Event::listen(
            MissionAssigned::class,
            function (MissionAssigned $event) use (&$received): void {
                $received[] = $event->missionId;
            }
        );

        DB::beginTransaction();

        try {
            MissionAssigned::dispatch(
                missionId: 43,
                driverId: 7,
                title: 'Mission validée',
                reference: 'GML-COMMIT-001',
            );

            $this->assertSame([], $received);

            DB::commit();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }

        $this->assertSame([43], $received);
    }
}
