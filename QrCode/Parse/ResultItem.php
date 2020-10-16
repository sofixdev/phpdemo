<?php

namespace App\Services\QrCode\Parse;



use App\Models\Retailer\Product;

class ResultItem
{

    /** @var string */
    private $title = '';

    /** @var float */
    private $price = 0;

    /** @var float */
    private $quantity = 0;

    /** @var float */
    private $sum = 0;

    /** @var Product */
    private $product;

    /**
     * ResultItem constructor.
     *
     * @param $title
     * @param $price
     * @param $quantity
     * @param $sum
     */
    public function __construct($title, $price, $quantity, $sum)
    {
        $this->title    = trim($title);
        $this->price    = $this->floatValue($price);
        $this->quantity = $this->floatValue($quantity);
        $this->sum      = $this->floatValue($sum);

        if ($this->sum == 0 && ($this->price * $this->quantity > 0)) {
            $this->sum = $this->price * $this->quantity;
        }
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return float
     */
    public function getPrice(): float
    {
        return $this->price;
    }

    /**
     * @return float
     */
    public function getQuantity(): float
    {
        return $this->quantity;
    }

    /**
     * @return float
     */
    public function getSum(): float
    {
        return $this->sum;
    }

    protected function floatValue($value)
    {
        return floatval(preg_replace('/[^\d+\.]/', '', str_replace(',', '.', $value)));
    }

    protected function intValue($value) {
        return intval(preg_replace('/[^\d+]/', '', $value));
    }

    /**
     * @return Product
     */
    public function getProduct(): Product
    {
        return $this->product;
    }

    /**
     * @param Product $product
     */
    public function setProduct(Product $product): void
    {
        $this->product = $product;
    }




}
