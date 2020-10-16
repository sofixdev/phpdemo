<?php


namespace App\Services\ExcelExport\PaymentChecker;


use App\Services\GoogleOAuth\ClientManager;
use Carbon\Carbon;

class Android extends AbstractPaymentChecker
{

    const PURCHASE_PURCHASED = 0;
    const PURCHASE_CANCELED = 1;

    protected function performCheck()
    {
        $googleOAuth = new ClientManager();
        $client = $googleOAuth->getClient();
        $service = new \Google_Service_AndroidPublisher($client);
        dump($this->exportRequest->toArray());
        $checkResult = $service->purchases_products->get(
            $this->exportRequest->android_package_name,
            $this->exportRequest->android_product_id,
            $this->exportRequest->android_payment_token
        );
        dump($checkResult);
        if (!$checkResult instanceof \Google_Service_AndroidPublisher_ProductPurchase) {
            throw new \Exception('Unexpected check result class: "' . get_class($checkResult) . '"');
        }
        $purchaseState = $checkResult->getPurchaseState();
        if ($purchaseState == self::PURCHASE_CANCELED) {
            $this->exportRequest->payment_canceled_at = Carbon::now();
            $this->exportRequest->save();
            return false;
        }
        $result = $purchaseState === self::PURCHASE_PURCHASED && $checkResult->getConsumptionState() === 0;

        if ($checkResult->getAcknowledgementState() === 0) {
            $acknowledgeRequest = new \Google_Service_AndroidPublisher_ProductPurchasesAcknowledgeRequest();
            $acknowledgeRequest->setDeveloperPayload('{}');
            $service->purchases_products->acknowledge(
                $this->exportRequest->android_package_name,
                $this->exportRequest->android_product_id,
                $this->exportRequest->android_payment_token,
                $acknowledgeRequest
            );
        }
        return $result;
    }



}
