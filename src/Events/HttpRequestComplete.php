<?php

namespace AnourValar\HttpClient\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** @psalm-suppress UndefinedTrait */
class HttpRequestComplete
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var string
     */
    public $uri;

    /**
     * @var string
     */
    public $method;

    /**
     * @var int
     */
    public $startedAt;

    /**
     * @var int
     */
    public $finishedAt;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(string $uri, string $method, int $startedAt, int $finishedAt)
    {
        $this->uri = $uri;
        $this->method = $method;
        $this->startedAt = $startedAt;
        $this->finishedAt = $finishedAt;
    }
}
