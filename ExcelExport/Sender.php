<?php


namespace App\Services\ExcelExport;


use App\Models\ExcelExportRequest;
use App\Notifications\ExcelExport;

class Sender
{

    /** @var ExcelExportRequest */
    protected $exportRequest;

    /**
     * @param ExcelExportRequest $exportRequest
     */
    public function __construct(ExcelExportRequest $exportRequest)
    {
        $this->exportRequest = $exportRequest;
    }

    public function send()
    {
        $this->exportRequest->notify(new ExcelExport($this->exportRequest));
    }


}
