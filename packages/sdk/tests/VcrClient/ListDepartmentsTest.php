<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Language;
use BlobSolutions\VcrAm\Model\DepartmentListItem;
use BlobSolutions\VcrAm\Model\DepartmentLocalizedTitle;
use BlobSolutions\VcrAm\TaxRegime;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

function makeDepartmentListJson(): string
{
    return json_encode([
        [
            'internalId' => 1,
            'externalId' => 'dept-groceries',
            'taxRegime' => 'vat',
            'title' => [
                'hy' => ['language' => 'hy', 'content' => 'Մթերք'],
                'ru' => ['language' => 'ru', 'content' => 'Продукты'],
                'en' => ['language' => 'en', 'content' => 'Groceries'],
            ],
        ],
        [
            'internalId' => 2,
            'externalId' => null,
            'taxRegime' => 'turnover_tax',
            'title' => [
                'hy' => ['language' => 'hy', 'content' => 'Ծառայություն'],
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}

it('parses departments with tax regime and localised titles', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], makeDepartmentListJson()));

    $result = $client->listDepartments();

    Assert::assertCount(2, $result);
    [$first, $second] = $result;

    expect($first)->toBeInstanceOf(DepartmentListItem::class)
        ->and($first->internalId)->toBe(1)
        ->and($first->externalId)->toBe('dept-groceries')
        ->and($first->taxRegime)->toBe(TaxRegime::Vat);

    Assert::assertArrayHasKey('hy', $first->title);
    Assert::assertArrayHasKey('ru', $first->title);
    expect($first->title['hy'])->toBeInstanceOf(DepartmentLocalizedTitle::class)
        ->and($first->title['hy']->language)->toBe(Language::Armenian)
        ->and($first->title['hy']->content)->toBe('Մթերք');

    expect($second->externalId)->toBeNull()
        ->and($second->taxRegime)->toBe(TaxRegime::TurnoverTax)
        ->and($second->title)->toHaveCount(1);
});

it('sends a GET request to /departments with the X-API-Key header', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], '[]'));

    $client->listDepartments();

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getMethod())->toBe('GET')
        ->and((string) $request->getUri())->toBe('https://vcr.am/api/v1/departments')
        ->and($request->getHeaderLine('X-API-Key'))->toBe('test-key');
});

it('accepts an empty list', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], '[]'));

    $result = $client->listDepartments();

    expect($result)->toBe([]);
});
