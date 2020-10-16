<?php


namespace App\Services\ExcelExport\PaymentChecker;


class Ios extends AbstractPaymentChecker
{

    protected function performCheck()
    {
        /** @todo  */
        return  rand(0,1) == 1;
    }

}
