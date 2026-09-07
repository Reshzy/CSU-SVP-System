<?php

test('reports ok with the current time to unauthenticated callers', function () {
    $this->travelTo('2026-01-15 08:30:00');

    $response = $this->get(route('health'));

    $response->assertOk();
    $response->assertExactJson([
        'status' => 'ok',
        'time' => '2026-01-15T08:30:00+00:00',
    ]);
});
