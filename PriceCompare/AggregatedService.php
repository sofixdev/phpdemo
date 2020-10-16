<?php

namespace App\Services\PriceCompare;

use App\Models\ReferenceProduct;
use App\Models\Retailer;
use App\Models\Retailer\Product as RetailerProduct;
use App\Models\Retailer\Shop;
use App\Repositories\DeviceRepository;
use App\Repositories\PriceCompareRepository;
use App\Repositories\Retailer\FirmRepository;
use App\Repositories\Retailer\ProductRepository;
use App\Repositories\Retailer\ShopRepository;
use Carbon\Carbon;

class AggregatedService
{

    /** @var DeviceRepository */
    protected $deviceRepository;

    /** @var PriceCompareRepository */
    protected $priceCompareRepository;

    /** @var ProductRepository */
    protected $productRepository;

    /** @var ShopRepository */
    protected $shopRepository;

    public function __construct(DeviceRepository $deviceRepository, PriceCompareRepository $priceCompareRepository, ProductRepository $productRepository, ShopRepository $shopRepository)
    {
        $this->deviceRepository = $deviceRepository;
        $this->priceCompareRepository = $priceCompareRepository;
        $this->productRepository = $productRepository;
        $this->shopRepository = $shopRepository;
    }

    /**
     * @param $retailerProductUuid
     * @return array
     * @throws \Exception
     */
    public function compare($retailerProductUuid)
    {

        $userId = $this->deviceRepository->getCurrentDeviceUserId();

        /** @var RetailerProduct $retailerProduct */
        $retailerProduct = $this->productRepository->find($retailerProductUuid);

        if (!$retailerProduct) {
            throw new \Exception('Товар не найден');
        }

        $retailerProductUuids = [$retailerProductUuid];
        if ($retailerProduct->verified_reference_product instanceof ReferenceProduct) {
            foreach ($retailerProduct->verified_reference_product->retailer_products as $referenceRetailerProduct) {
                if (!$referenceRetailerProduct->reference_product_verified_at) {
                    continue;
                }
                $retailerProductUuids[] = $referenceRetailerProduct->uuid;
            }
        }

        foreach ($this->productRepository->findRetailerOtherFirmsSameProduct($retailerProduct) as $otherFirmProduct) {
            $retailerProductUuids[] = $otherFirmProduct->uuid;
        }

        $aggregated = $this->priceCompareRepository->getOthersAggregated($userId, $retailerProductUuids);

        return [
            'mine' => $this->addonShopFields($this->priceCompareRepository->getUserAggregated($userId, $retailerProductUuids)),
            'others' => $this->addonFields($aggregated, $userId, $retailerProductUuids)
        ];

    }

    protected function addonFields($result, $userId, $retailerProductUuids)
    {
        foreach ($result as &$row) {
            $userLastItem = $this->priceCompareRepository->getUserAggregated($userId, $retailerProductUuids, $row->retailer_shop_uuid);
            $this->addonShopFields($row);
            $row->user_last_price = ($userLastItem) ? $userLastItem->price : null;
            $row->user_last_price_is_old = ($userLastItem) ? (time() - Carbon::parse($userLastItem->check_date)->getTimestamp() > (3600 * 24 * 30)) : null;
            $row->user_last_date = ($userLastItem) ? $userLastItem->check_date : null;
            $row->user_last_check_uuid = ($userLastItem) ? $userLastItem->check_uuid : null;


        }
        return $result;
    }

    protected function addonShopFields($row)
    {
        if (is_null($row)) {
            return $row;
        }
        /** @var Shop $shop */
        $shop = $this->shopRepository->find($row->retailer_shop_uuid);
        $firm = $shop->firm;
        $row->retailer_uuid = $firm->retailer_uuid;
        $row->retailer_shop_main_title = $this->getRowShopMainTitle($shop);
        $row->retailer_shop_sub_title = $this->getRowShopSubTitle($shop);
        $row->retailer_shop_address = $shop->display_address;
        $row->retailer_shop_inn = $firm->inn;
        return $row;
    }

    public function getRowShopMainTitle(Shop $shop)
    {
        if ($shop->firm->retailer instanceof Retailer) {
            return $shop->firm->retailer->title;
        }
        $result = $shop->display_title;
        if (preg_match('/^[\d]+$/ui', $result) || preg_match('/кассир/ui', $result) || preg_match('|\d{6}\,|', $result) ) {
            $result = $shop->firm->display_title;
        }
        return $result;
    }

    public function getRowShopSubTitle(Shop $shop)
    {
        $parts = [];
        $parts[] = $shop->display_title;
        if ($shop->firm) {
            $parts[] = $shop->firm->display_title;
        }
        $parts = array_unique($parts);
        return implode(', ', $parts);
    }

}
