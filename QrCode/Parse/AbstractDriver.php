<?php

namespace App\Services\QrCode\Parse;

use App\Models\QrCode;
use App\Repositories\ReferenceProductRepository;
use App\Repositories\Retailer\FirmRepository;
use App\Repositories\Retailer\ProductRepository;
use App\Repositories\Retailer\ShopRepository;

abstract class AbstractDriver
{
    use QrCodesLoggingTrait {
        log as traitLog;
    }

    /** @var QrCode */
    protected $qrCode;

    /** @var Result */
    protected $parsedResult;

    /** @var FirmRepository */
    protected $firmRepository;

    /** @var ShopRepository */
    protected $shopRepository;

    /** @var ProductRepository */
    protected $productRepository;

    /** @var ReferenceProductRepository */
    protected $referenceProductRepository;

    abstract public function handleQrCode();

    abstract public function getDriverName();

    public function __construct(
        FirmRepository $firmRepository = null,
        ShopRepository $shopRepository = null,
        ProductRepository $productRepository = null,
        ReferenceProductRepository $referenceProductRepository = null
    ) {
        $this->firmRepository             = $firmRepository;
        $this->shopRepository             = $shopRepository;
        $this->productRepository          = $productRepository;
        $this->referenceProductRepository = $referenceProductRepository;
    }

    public function getQrCode()
    {
        return $this->qrCode;
    }


    public function clearData()
    {
        $this->parsedResult = null;
    }


    public function setQrCode(QrCode $qrCode)
    {
        $this->qrCode = $qrCode;
    }

    /**
     * @throws \Exception
     */
    public function identify()
    {
        $this->identifyFirm();
        $this->identifyShop();
        $this->identifyProducts();
    }

    public function getParseResult()
    {
        return $this->parsedResult;
    }


    /**
     * @throws \Exception
     */
    protected function identifyFirm()
    {
        if (!$this->getParseResult()->getFirmInn()) {
            throw new \Exception('Fail to identify firm: INN is empty');
        }
        $firm = $this->firmRepository->findOrCreate($this->parsedResult->getFirmInn(), $this->parsedResult->getFirmTitle());
        $this->parsedResult->setFirm($firm);
    }

    /**
     * @throws \Exception
     */
    protected function identifyShop()
    {
        $firm = $this->parsedResult->getFirm();
        if (is_null($firm)) {
            throw new \Exception('Fail to identify shop: Firm not found');
        }
        if (!$this->parsedResult->getShopTitle()) {
            throw new \Exception('Fail to identify shop: Shop title is empty');
        }
        $shop = $this->shopRepository->findOrCreate($firm, $this->parsedResult->getShopTitle(), $this->parsedResult->getShopAddress());
        $this->parsedResult->setShop($shop);
    }

    /**
     * @throws \Exception
     */
    protected function identifyProducts()
    {

        $firm = $this->parsedResult->getFirm();
        if (!$firm) {
            throw new \Exception('Firm is not found');
        }

        foreach ($this->parsedResult->getItems() as $item) {
            $product = $this->productRepository->findOrCreate($firm, $item->getTitle());
            $item->setProduct($product);
        }
    }

    protected function log($message, $context = [], $method = 'info')
    {
        if ($this->qrCode instanceof QrCode && empty($context)) {
            $context = [$this->qrCode->uuid];
        }
        $this->traitLog($message, $context);
    }


}
