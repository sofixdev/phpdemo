<?php

namespace App\Services\QrCode\Parse;



use App\Models\Retailer\Firm;
use App\Models\Retailer\Shop;

class Result
{

    /**
     * @var ResultItem[]
     */
    protected $items = [];

    /** @var string */
    protected $firmTitle = '';

    /** @var string */
    protected $shopTitle = '';

    /** @var string */
    protected $shopAddress = '';

    /** @var string */
    protected $firmInn = '';

    /** @var Firm */
    protected $firm;

    /** @var Shop */
    protected $shop;

    /**
     * Result constructor.
     *
     * @param string $firmTitle
     * @param string $shopTitle
     * @param string $shopAddress
     * @param string $firmInn
     */
    public function __construct($firmTitle, $firmInn, $shopTitle, $shopAddress)
    {
        $this->firmTitle   = $firmTitle;
        $this->firmInn     = $firmInn;
        $this->shopTitle   = $shopTitle ? $shopTitle : ($firmTitle ? $firmTitle : $firmInn);
        $this->shopAddress = $shopAddress;
    }


    public function getItems()
    {
        return $this->items;
    }

    public function addItem(ResultItem $item)
    {
        $this->items[] = $item;
    }

    /**
     * @return string
     */
    public function getFirmInn(): string
    {
        return $this->firmInn;
    }

    /**
     * @return string
     */
    public function getFirmTitle(): ?string
    {
        return $this->firmTitle;
    }

    /**
     * @return string
     */
    public function getShopTitle(): ?string
    {
        return $this->shopTitle;
    }

    /**
     * @return string
     */
    public function getShopAddress(): ?string
    {
        return $this->shopAddress;
    }

    /**
     * @return Firm
     */
    public function getFirm(): Firm
    {
        return $this->firm;
    }

    /**
     * @return Shop
     */
    public function getShop(): ?Shop
    {
        return $this->shop;
    }

    /**
     * @param Firm $firm
     */
    public function setFirm(Firm $firm): void
    {
        $this->firm = $firm;
    }

    /**
     * @param Shop $shop
     */
    public function setShop(Shop $shop): void
    {
        $this->shop = $shop;
    }






}
