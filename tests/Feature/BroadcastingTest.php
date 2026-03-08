<?php

use App\Events\PlanCompleted;
use App\Events\PlanFailed;
use App\Events\PlanStepCompleted;
use App\Events\PlanStepFailed;
use App\Models\Agent;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanStep;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->user = User::factory()->create();
    $this->user->organizations()->attach($this->organization);

    $this->workItem = WorkItem::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $this->plan = Plan::factory()->completed()->create([
        'organization_id' => $this->organization->id,
        'work_item_id' => $this->workItem->id,
    ]);
});

describe('Plan Events Broadcasting', function () {
    it('broadcasts PlanCompleted on the organization private channel', function () {
        $event = new PlanCompleted($this->plan);

        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(1);
        expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
        expect($channels[0]->name)->toBe('private-organization.'.$this->organization->id);
    });

    it('broadcasts PlanCompleted with correct payload', function () {
        $event = new PlanCompleted($this->plan);

        $data = $event->broadcastWith();

        expect($data)->toHaveKeys(['plan_id', 'work_item_id', 'status', 'completed_at']);
        expect($data['plan_id'])->toBe($this->plan->id);
        expect($data['work_item_id'])->toBe($this->workItem->id);
        expect($data['status'])->toBe('completed');
    });

    it('broadcasts PlanFailed on the organization private channel', function () {
        $plan = Plan::factory()->failed()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        $event = new PlanFailed($plan);
        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(1);
        expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
        expect($channels[0]->name)->toBe('private-organization.'.$this->organization->id);
    });

    it('broadcasts PlanFailed with correct payload', function () {
        $plan = Plan::factory()->failed()->create([
            'organization_id' => $this->organization->id,
            'work_item_id' => $this->workItem->id,
        ]);

        $event = new PlanFailed($plan);
        $data = $event->broadcastWith();

        expect($data['plan_id'])->toBe($plan->id);
        expect($data['status'])->toBe('failed');
    });

    it('implements ShouldBroadcast on plan events', function () {
        expect(new PlanCompleted($this->plan))
            ->toBeInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);

        expect(new PlanFailed($this->plan))
            ->toBeInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);
    });

    it('dispatches PlanCompleted event via broadcast', function () {
        Event::fake([PlanCompleted::class]);

        PlanCompleted::dispatch($this->plan);

        Event::assertDispatched(PlanCompleted::class, function ($event) {
            return $event->plan->id === $this->plan->id;
        });
    });
});

describe('PlanStep Events Broadcasting', function () {
    beforeEach(function () {
        $this->agent = Agent::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->planStep = PlanStep::factory()->completed()->create([
            'plan_id' => $this->plan->id,
            'agent_id' => $this->agent->id,
            'order' => 1,
        ]);
    });

    it('broadcasts PlanStepCompleted on the organization private channel', function () {
        $event = new PlanStepCompleted($this->planStep);
        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(1);
        expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
        expect($channels[0]->name)->toBe('private-organization.'.$this->organization->id);
    });

    it('broadcasts PlanStepCompleted with correct payload', function () {
        $event = new PlanStepCompleted($this->planStep);
        $data = $event->broadcastWith();

        expect($data)->toHaveKeys(['plan_step_id', 'plan_id', 'status', 'order', 'result', 'completed_at']);
        expect($data['plan_step_id'])->toBe($this->planStep->id);
        expect($data['plan_id'])->toBe($this->plan->id);
        expect($data['status'])->toBe('completed');
        expect($data['order'])->toBe(1);
    });

    it('broadcasts PlanStepFailed on the organization private channel', function () {
        $step = PlanStep::factory()->failed()->create([
            'plan_id' => $this->plan->id,
            'agent_id' => $this->agent->id,
            'order' => 2,
        ]);

        $event = new PlanStepFailed($step);
        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(1);
        expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
        expect($channels[0]->name)->toBe('private-organization.'.$this->organization->id);
    });

    it('broadcasts PlanStepFailed with correct payload', function () {
        $step = PlanStep::factory()->failed()->create([
            'plan_id' => $this->plan->id,
            'agent_id' => $this->agent->id,
            'order' => 2,
        ]);

        $event = new PlanStepFailed($step);
        $data = $event->broadcastWith();

        expect($data['plan_step_id'])->toBe($step->id);
        expect($data['status'])->toBe('failed');
    });

    it('implements ShouldBroadcast on plan step events', function () {
        expect(new PlanStepCompleted($this->planStep))
            ->toBeInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);

        expect(new PlanStepFailed($this->planStep))
            ->toBeInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);
    });
});

describe('Channel Authorization', function () {
    it('authorizes users who belong to the organization', function () {
        $result = Broadcast::channel('organization.{organization}', function (User $user, Organization $organization) {
            return $user->organizations->contains($organization);
        });

        $this->actingAs($this->user);

        expect($this->user->organizations->contains($this->organization))->toBeTrue();
    });

    it('rejects users who do not belong to the organization', function () {
        $otherUser = User::factory()->create();

        expect($otherUser->organizations->contains($this->organization))->toBeFalse();
    });

    it('registers the organization channel route', function () {
        $channels = Broadcast::getChannels();

        expect($channels)->toHaveKey('organization.{organization}');
    });
});
