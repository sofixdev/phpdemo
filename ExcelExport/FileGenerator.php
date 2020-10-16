<?php


namespace App\Services\ExcelExport;


use App\Models\ExcelExportRequest;
use App\Repositories\ExcelExportGeneratorRepository;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FileGenerator
{
    const FILES_STORAGE_PATH = 'app/excel_export';

    /** @var ExcelExportRequest */
    protected $exportRequest;

    /** @var Carbon */
    protected $periodFrom;

    /** @var Carbon */
    protected $periodTill;

    /** @var ExcelExportGeneratorRepository */
    protected $generatorRepository;

    /** @var Spreadsheet */
    protected $phpExcel;

    /** @var Worksheet */
    protected $operationsSheet;

    /** @var Worksheet */
    protected $expansesSheet;

    /** @var Worksheet */
    protected $categoriesSheet;

    public function __construct(ExcelExportGeneratorRepository $generatorRepository)
    {
        $this->generatorRepository = $generatorRepository;
    }

    public function setExportRequest(ExcelExportRequest $exportRequest)
    {
        $this->exportRequest = $exportRequest;
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function generate()
    {
        $this->setStartDate();
        $this->initParameters();
        $this->initExcelHandler();
        $this->createSheets();
        $this->generateOperationsSheet();
        $this->generateExpansesSheet();
        $this->generateCategoriesSheet();
        $this->finishFile();
        $this->saveFile();
        $this->setFinishDate();
    }

    protected function initParameters()
    {
        $this->periodFrom = Carbon::parse($this->exportRequest->period_from);
        $this->periodTill = Carbon::parse($this->exportRequest->period_till . ' 23:59:59');
    }

    protected function initExcelHandler()
    {
        $this->phpExcel = new Spreadsheet();
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function createSheets()
    {
        $this->phpExcel->removeSheetByIndex(0);
        $this->operationsSheet = $this->phpExcel->createSheet(0);
        $this->operationsSheet->setTitle('Операции');
        $this->setSheetHeader($this->operationsSheet, ['Дата' => 18, 'Комментарий' => 40, 'Сумма' => 14]);
        $this->expansesSheet = $this->phpExcel->createSheet(1);
        $this->expansesSheet->setTitle('Расходы подробно');
        $this->setSheetHeader($this->expansesSheet, ['Дата' => 18, 'Описание' => 60, 'Категория' => 40, 'Цена' => 14, 'Количество' => 14, 'Сумма' => 14, 'Магазин' => 30]);
        $this->categoriesSheet = $this->phpExcel->createSheet(2);
        $this->categoriesSheet->setTitle('Расходы по категориям');
        $this->setSheetHeader($this->categoriesSheet, ['Категории' => 60, 'Категория' => 40, 'Сумма' => 14]);
    }

    protected function generateOperationsSheet()
    {
        $rowId = 1;
        foreach ($this->generatorRepository->getOperations($this->exportRequest->user, $this->periodFrom, $this->periodTill) as $operation) {
            $rowId ++;
            $this->setRow($this->operationsSheet, $rowId, $operation);
        }

    }

    protected function generateExpansesSheet()
    {
        $rowId = 1;
        foreach ($this->generatorRepository->getExpanses($this->exportRequest->user, $this->periodFrom, $this->periodTill) as $expanse) {
            $rowId ++;
            $this->setRow($this->expansesSheet, $rowId, $expanse);
        }
    }

    protected function generateCategoriesSheet()
    {
        $rowId = 1;
        foreach ($this->generatorRepository->getCategories($this->exportRequest->user, $this->periodFrom, $this->periodTill) as $category) {
            $rowId ++;
            $this->setRow($this->categoriesSheet, $rowId, $category);
        }
    }

    protected function finishFile()
    {
        $this->phpExcel->setActiveSheetIndex(0);
        /** @var Worksheet $sheet */
        foreach ([
            $this->operationsSheet,
            $this->expansesSheet,
            $this->categoriesSheet
        ] as $sheet) {
            $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        }
    }

    protected function saveFile()
    {
        $writer = new Xlsx($this->phpExcel);
        $writer->save($this->exportRequest->result_file_path);
    }

    protected function setStartDate()
    {
        $this->exportRequest->file_generator_start_at = Carbon::now();
        $this->exportRequest->save();
    }

    protected function setFinishDate()
    {
        $this->exportRequest->file_generator_finish_at = Carbon::now();
        $this->exportRequest->save();
    }

    private function setSheetHeader(Worksheet $sheet, $headers)
    {
        $headerKey = 0;
        foreach ($headers as $header => $headerWidth) {
            $cell = $sheet->getCellByColumnAndRow($headerKey + 1, 1);
            $style = $cell->getStyle();
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('eeeeee');
            $style->getFont()->setBold(true);
            $style->getFont()->getColor()->setARGB('333333');
            $cell->setValue($header);
            $sheet->getColumnDimension($cell->getColumn())->setWidth($headerWidth + 0.71);
            $headerKey ++;
        }
    }

    private function setRow(Worksheet $sheet, $rowId, $rowItems)
    {
        $rowItemsValues = array_values($rowItems);
        $titleCount = count(array_filter($sheet->rangeToArray('A1:Z1')[0]));
        foreach ($rowItemsValues as $rowItemKey => $rowItem) {
            $cell = $sheet->getCellByColumnAndRow($rowItemKey + 1, $rowId);
            if ($rowItem instanceof Carbon) {
                $rowItem = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($rowItem);
                $cell->getStyle()->getNumberFormat()->setFormatCode('dd mmm yyyy hh:mm');
            }
            elseif(is_float($rowItem)) {
                $cell->getStyle()->getNumberFormat()->setFormatCode('#,##0.00');
            }
            $cell->setValue($rowItem);
            if ($rowItemKey >= $titleCount) {
                $sheet->getColumnDimension($cell->getColumn())->setVisible(false);
//                $style = $cell->getStyle();
//                $style->getFont()->getColor()->setARGB('FFFFFF');
            }
        }
    }

}
