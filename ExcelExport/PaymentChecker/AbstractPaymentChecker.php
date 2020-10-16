<?php


namespace App\Services\ExcelExport\PaymentChecker;


use App\Models\ExcelExportRequest;
use Carbon\Carbon;

abstract class AbstractPaymentChecker
{

    /** @var ExcelExportRequest */
    protected $exportRequest;

    abstract protected function performCheck();

    /**
     * @param ExcelExportRequest $exportRequest
     */
    public function __construct(ExcelExportRequest $exportRequest)
    {
        $this->exportRequest = $exportRequest;
    }


    public function check()
    {
        if ($this->exportRequest->payment_finished_at) {
            return;
        }
        $this->exportRequest->payment_last_check_at = Carbon::now();
        $this->exportRequest->save();

        if ($this->performCheck()) {
            $this->exportRequest->payment_finished_at = Carbon::now();
            $this->exportRequest->save();
        }

    }

}
