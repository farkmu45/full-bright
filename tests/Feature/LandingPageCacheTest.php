<?php

use App\Analytics\EventType;
use Illuminate\Support\Str;

function csrfTokenFrom(string $html): string
{
    preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $matches);

    return $matches[1] ?? '';
}

function visitorIdFrom(string $html): string
{
    preg_match('/"visitorId":"([^"]+)"/', $html, $matches);

    return $matches[1] ?? '';
}

test('cached landing html is personalized for every visitor', function () {
    $first = $this->get('/?utm_source=first');
    $first->assertOk();
    $firstHtml = (string) $first->getContent();

    $this->flushSession();

    $second = $this->get('/?utm_source=second');
    $second->assertOk();
    $secondHtml = (string) $second->getContent();

    expect(csrfTokenFrom($secondHtml))->not->toBe('')
        ->and(csrfTokenFrom($secondHtml))->not->toBe(csrfTokenFrom($firstHtml))
        ->and(csrfTokenFrom($secondHtml))->toBe(session()->token())
        ->and(Str::isUuid(visitorIdFrom($secondHtml)))->toBeTrue()
        ->and(visitorIdFrom($secondHtml))->not->toBe(visitorIdFrom($firstHtml))
        ->and($secondHtml)->not->toContain(visitorIdFrom($firstHtml))
        ->and($secondHtml)->toContain(json_encode('/?utm_source=second'))
        ->and($secondHtml)->not->toContain('utm_source=first')
        ->and($secondHtml)->not->toContain('__PBM_CSRF_TOKEN__')
        ->and($secondHtml)->not->toContain('__PBM_VISITOR_ID__')
        ->and($secondHtml)->not->toContain('__PBM_REQUEST_URI__');

    $this->withMiddleware()
        ->withHeader('X-CSRF-TOKEN', csrfTokenFrom($secondHtml))
        ->postJson('/analytics/track', [
            'event_type' => EventType::Intent->value,
            'event_data' => ['event_id' => (string) Str::uuid(), 'zone' => 'hero', 'action' => 'scroll'],
        ])->assertCreated();
});
