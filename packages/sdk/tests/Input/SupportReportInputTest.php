<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Input\SupportReportInput;

it('sends only what was given', function (): void {
    expect((new SupportReportInput(message: 'It broke'))->jsonSerialize())
        ->toBe(['message' => 'It broke']);

    expect((new SupportReportInput(context: ['plugin' => '0.3.0']))->jsonSerialize())
        ->toBe(['context' => ['plugin' => '0.3.0']]);
});

it('leaves out a blank contact and a blank message', function (): void {
    $input = new SupportReportInput(message: '  ', context: ['a' => 1], contact: ' ');

    expect($input->jsonSerialize())->toBe(['context' => ['a' => 1]]);
});

it('refuses a report with nothing in it', function (): void {
    $expected = 'needs a message, a context, or both';

    expect(fn () => new SupportReportInput())->toThrow(InvalidArgumentException::class, $expected);
    expect(fn () => new SupportReportInput(message: "  \n"))->toThrow(InvalidArgumentException::class, $expected);
    expect(fn () => new SupportReportInput(context: []))->toThrow(InvalidArgumentException::class, $expected);
});

it('refuses a message over the limit and accepts one at it', function (): void {
    $atLimit = str_repeat('ж', SupportReportInput::MAX_MESSAGE_LENGTH);

    expect((new SupportReportInput(message: $atLimit))->message)->toBe($atLimit);
    expect(fn () => new SupportReportInput(message: $atLimit . 'ж'))
        ->toThrow(InvalidArgumentException::class, 'message must be at most 4000 characters, got 4001.');
});

it('refuses a contact over the limit', function (): void {
    expect(fn () => new SupportReportInput(message: 'x', contact: str_repeat('a', 201)))
        ->toThrow(InvalidArgumentException::class, 'contact must be at most 200 characters, got 201.');
});

it('refuses too many trace ids', function (): void {
    $ids = array_fill(0, 51, str_repeat('a', 32));

    expect(fn () => new SupportReportInput(message: 'x', traceIds: $ids))
        ->toThrow(InvalidArgumentException::class, 'at most 50 entries, got 51.');
});

it('refuses a malformed trace id', function (string $traceId): void {
    expect(fn () => new SupportReportInput(message: 'x', traceIds: [$traceId]))
        ->toThrow(InvalidArgumentException::class, '32 lowercase hex characters');
})->with([
    'uppercase' => [str_repeat('A', 32)],
    'short' => [str_repeat('a', 31)],
    'trailing newline' => [str_repeat('a', 32) . "\n"],
]);
