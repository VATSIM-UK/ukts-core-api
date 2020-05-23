<?php

namespace Tests\Unit\Position;

use App\Modules\Position\Exceptions\PositionAlreadyAssignedForTrainingException;
use App\Modules\Position\Exceptions\PositionNotAssignedForTrainingException;
use App\Modules\Position\Position;
use App\Modules\Position\Services\TrainingPositionService;
use App\Modules\Position\TrainingPosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingPositionServiceUnitTest extends TestCase
{
    use RefreshDatabase;

    private $service;
    private $position;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(TrainingPositionService::class);

        $this->position = factory(Position::class)->create();
    }

    /** @test */
    public function itPassesCheckWhenNoActiveAssignmentsForGivenPositionExist()
    {
        $this->assertFalse($this->service->checkHasExistingActivePositionAssignments($this->position));
    }

    /** @test */
    public function itDetectsWhenActivePositionAssignmentExists()
    {
        factory(TrainingPosition::class)->create(['position_id' => $this->position->id]);
        $this->assertTrue($this->service->checkHasExistingActivePositionAssignments($this->position));
    }

    /** @test */
    public function itPassesCheckIfPreviousAssignmentHasBeenSoftDeleted()
    {
        $assignment = factory(TrainingPosition::class)->create(['position_id' => $this->position->id]);
        $assignment->delete();

        $this->assertFalse($this->service->checkHasExistingActivePositionAssignments($this->position));
    }

    /** @test */
    public function itCreatesAssignmentGivenValidConditions()
    {
        $this->service->createAssignment($this->position);

        $this->assertDatabaseHas('training_positions', [
            'position_id' => $this->position->id,
            'deleted_at' => null,
        ]);
    }

    /** @test */
    public function itDoesntCreateAndThrowsExceptionWhenExistingAssignmentExists()
    {
        factory(TrainingPosition::class)->create(['position_id' => $this->position->id]);
        $this->expectException(PositionAlreadyAssignedForTrainingException::class);

        $this->service->createAssignment($this->position);

        $this->assertDatabaseMissing('training_positions', [
            'position_id' => $this->position->id,
            'deleted_at' => null,
        ]);
    }

    /** @test */
    public function itRemovesPositionsFromTraining()
    {
        factory(TrainingPosition::class)->create(['position_id' => $this->position->id]);

        $this->service->removeAssignment($this->position);

        $this->assertDatabaseHas('training_positions', [
            'position_id' => $this->position->id,
        ]);
    }

    /** @test */
    public function itDoesntRemovePositionFromTrainingIfDoesntAlreadyExist()
    {
        $this->expectException(PositionNotAssignedForTrainingException::class);

        $this->service->removeAssignment($this->position);
    }
}
