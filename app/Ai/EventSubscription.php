<?php

namespace App\Ai;

class EventSubscription
{
    public function __construct(
        public string $eventType,
        public ?string $action = null,
        public array $filters = [],
    ) {}

    /**
     * @param  array{event: string, filters?: array<string, mixed>}  $data
     */
    public static function fromArray(array $data): self
    {
        $event = $data['event'];
        $action = null;

        if (str_contains($event, '.')) {
            [$event, $action] = explode('.', $event, 2);
        }

        return new self(
            eventType: $event,
            action: $action,
            filters: $data['filters'] ?? [],
        );
    }

    /**
     * @return array{event: string, filters: array<string, mixed>}
     */
    public function toArray(): array
    {
        $event = $this->action ? "{$this->eventType}.{$this->action}" : $this->eventType;

        return [
            'event' => $event,
            'filters' => $this->filters,
        ];
    }

    /**
     * @param  array<string, mixed>  $context  Keys: labels (array), base_branch (string), branches (array)
     */
    public function matches(string $eventType, ?string $action, array $context = []): bool
    {
        if ($this->eventType !== $eventType) {
            return false;
        }

        if ($this->action !== null && $this->action !== $action) {
            return false;
        }

        if (! empty($this->filters['labels']) && ! empty($context['labels'])) {
            $contextLabels = array_map('strtolower', $context['labels']);
            $filterLabels = array_map('strtolower', $this->filters['labels']);

            if (empty(array_intersect($filterLabels, $contextLabels))) {
                return false;
            }
        } elseif (! empty($this->filters['labels']) && empty($context['labels'])) {
            return false;
        }

        if (! empty($this->filters['base_branch'])) {
            if (($context['base_branch'] ?? null) !== $this->filters['base_branch']) {
                return false;
            }
        }

        if (! empty($this->filters['branches'])) {
            $branch = $context['branch'] ?? null;

            if ($branch === null) {
                return false;
            }

            $matchesBranch = false;

            foreach ($this->filters['branches'] as $pattern) {
                if (fnmatch($pattern, $branch)) {
                    $matchesBranch = true;
                    break;
                }
            }

            if (! $matchesBranch) {
                return false;
            }
        }

        return true;
    }
}
