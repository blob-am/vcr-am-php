<?php

declare(strict_types=1);

namespace BlobSolutions\LaravelVcrAm\Facades;

use BlobSolutions\VcrAm\VcrClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static list<\BlobSolutions\VcrAm\Model\CashierListItem> listCashiers()
 * @method static \BlobSolutions\VcrAm\Model\RegisterSaleResponse registerSale(\BlobSolutions\VcrAm\Input\RegisterSaleInput $input)
 * @method static \BlobSolutions\VcrAm\Model\RegisterSaleRefundResponse registerSaleRefund(\BlobSolutions\VcrAm\Input\RegisterSaleRefundInput $input)
 * @method static \BlobSolutions\VcrAm\Model\RegisterPrepaymentResponse registerPrepayment(\BlobSolutions\VcrAm\Input\RegisterPrepaymentInput $input)
 * @method static \BlobSolutions\VcrAm\Model\PrepaymentDetail getPrepayment(int $prepaymentId)
 * @method static \BlobSolutions\VcrAm\Model\RegisterPrepaymentRefundResponse registerPrepaymentRefund(\BlobSolutions\VcrAm\Input\RegisterPrepaymentRefundInput $input)
 * @method static \BlobSolutions\VcrAm\Model\CreateCashierResponse createCashier(\BlobSolutions\VcrAm\Input\CreateCashierInput $input)
 * @method static \BlobSolutions\VcrAm\Model\CreateDepartmentResponse createDepartment(\BlobSolutions\VcrAm\Input\CreateDepartmentInput $input)
 * @method static \BlobSolutions\VcrAm\Model\CreateOfferResponse createOffer(\BlobSolutions\VcrAm\Input\CreateOfferInput $input)
 * @method static list<\BlobSolutions\VcrAm\Model\ClassifierSearchItem> searchClassifier(string $query, \BlobSolutions\VcrAm\OfferType $type, \BlobSolutions\VcrAm\Language $language)
 * @method static \BlobSolutions\VcrAm\Model\SaleDetail getSale(int $saleId)
 *
 * @see VcrClient
 */
final class VcrAm extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return VcrClient::class;
    }
}
