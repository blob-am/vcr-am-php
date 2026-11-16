<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Input\SupportReportInput;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

it('posts the report and returns the reference', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(
        201,
        ['Content-Type' => 'application/json'],
        '{"reference":"7K4M-2QXD","createdAt":"2026-11-16T12:58:14.466Z"}',
    ));

    $receipt = $client->submitSupportReport(new SupportReportInput(
        message: 'Заказ 8402 оплачен, а чек не вышел',
        context: ['order' => ['status' => 'pending']],
        traceIds: ['f05e07ec81806aa151500c414d14d53b'],
        contact: 't.me/shop_owner',
    ));

    expect($receipt->reference)->toBe('7K4M-2QXD')
        ->and($receipt->createdAt)->toBe('2026-11-16T12:58:14.466Z');

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getMethod())->toBe('POST')
        ->and($request->getUri()->getPath())->toBe('/api/v1/support/reports')
        ->and(json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR))->toBe([
            'message' => 'Заказ 8402 оплачен, а чек не вышел',
            'contact' => 't.me/shop_owner',
            'context' => ['order' => ['status' => 'pending']],
            'traceIds' => ['f05e07ec81806aa151500c414d14d53b'],
        ]);
});

it('surfaces the size refusal as an API exception', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(
        413,
        ['Content-Type' => 'application/json'],
        '{"error":"`context` is 300011 bytes; the limit is 262144. Send fewer log entries.","code":"context_too_large"}',
    ));

    try {
        $client->submitSupportReport(new SupportReportInput(context: ['dump' => 'x']));
    } catch (VcrApiException $e) {
        expect($e->statusCode)->toBe(413)
            ->and($e->apiErrorMessage)->toContain('limit is 262144');

        return;
    }

    Assert::fail('Expected a VcrApiException.');
});
