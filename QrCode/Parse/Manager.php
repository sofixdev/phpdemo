<?php


namespace App\Services\QrCode\Parse;

use App\Models\Check;
use App\Models\QrCode;
use App\Repositories\CheckRepository;
use App\Repositories\QrCodeRepository;

class Manager {

    use QrCodesLoggingTrait;

    const QR_CODE_MAX_OFD_PARSE_COUNT = 8; // Через какое количество попыток прекращать использовать сервисы ОФД (8 попыток == 36 часов)

    /** @var QrCodeRepository */
    protected $qrCodeRepository;

    /** @var CheckRepository */
    protected $checkRepository;

    /** @var AbstractDriver[] */
    protected $drivers;

    /**
     * Manager constructor.
     * @param QrCodeRepository $qrCodeRepository
     * @param CheckRepository $checkRepository
     */
    public function __construct(QrCodeRepository $qrCodeRepository, CheckRepository $checkRepository)
    {
        $this->qrCodeRepository = $qrCodeRepository;
        $this->checkRepository = $checkRepository;
    }

    public function checkQrCode(QrCode $qrCode)
    {
        $this->log('#### QR-code UUID: ' . $qrCode->uuid . ' (' . $qrCode->parse_count . '/' . QrCode::MAX_PARSE_COUNT . ' parse tries)', [$qrCode->uuid]);
        if ($qrCode->check()->count()) {
            $this->log('!!! QR-code already resolved', [$qrCode->uuid]);
            return;
        }
        if ($this->findExistingQrCode($qrCode)) {
            $this->log('=== Success by existing QR-code', [$qrCode->uuid]);
            return;
        }
        $drivers = $this->getDrivers();
        $driverClasses = $this->getQrCodeDriverClasses($qrCode);
        $this->qrCodeRepository->increaseCheckCount($qrCode);
        foreach ($driverClasses as $driverClassKey => $driverClass) {
            $driver = $drivers[$driverClass];
            $this->log('=== OFD Driver: [' . ($driverClassKey + 1) . '/' . count($driverClasses) . '] ' . $driver->getDriverName(), [$qrCode->uuid]);
            $driver->clearData();
            $driver->setQrCode($qrCode);
            try {
                $driver->handleQrCode();
            }
            catch (\Exception $e) {
                $this->log('!!! Check Driver parse ERROR: ' . $e->getMessage(), [$qrCode->uuid], 'error');
                continue;
            }
            $parseResult = $driver->getParseResult();
            if ($parseResult) {
                try {
                    $driver->identify();
                }
                catch (\Exception $e) {
                    $this->log('!!! Check Driver identify ERROR: ' . $e->getMessage(), [$qrCode->uuid], 'error');
                    continue;
                }
                $this->log('=== SUCCESS by ' . $driver->getDriverName(), [$qrCode->uuid]);
//                $this->log($parseResult);
                $check = $this->checkRepository->createCheck($qrCode, $parseResult);
                $this->qrCodeRepository->setSuccessParse($qrCode, get_class($driver), $check);
                return;
            }
            else {
                $this->log('!!! OFD Driver fail', [$qrCode->uuid]);
            }
        }
        $this->qrCodeRepository->setFailParse($qrCode);

    }

    protected function findExistingQrCode(QrCode $qrCode)
    {
        $existingQrCode = $this->qrCodeRepository->findExistingQrCode($qrCode);
        if ($existingQrCode instanceof QrCode && $existingQrCode->check instanceof Check) {
            if ($qrCode->user_id == $existingQrCode->user_id) { // Если исходный QR-код принадлежит тому же пользователю, тогда QR-код удаляется
                $qrCode->deleted = 1;
                $qrCode->save();
                return true;
            }
            $newCheck = $this->checkRepository->cloneCheck($existingQrCode->check, $qrCode);
            $this->qrCodeRepository->setSuccessParse($qrCode, $existingQrCode->parse_driver_class, $newCheck);
            return true;
        }
        return false;
    }

    protected function getDrivers()
    {
        if (is_null($this->drivers)) {
            $this->initDrivers();
        }
        return $this->drivers;
    }

    protected function getQrCodeDriverClasses(QrCode $qrCode)
    {
        $result = [];
        if ($qrCode->parse_count <= self::QR_CODE_MAX_OFD_PARSE_COUNT) {
            $result = array_merge($result, $this->getOfdDriverClasses());
        }
        $result = array_merge($result, $this->getFnsDriverClasses());
        $previousDriverClass = $this->qrCodeRepository->getPreviousQrCodeDriverClass($qrCode);
        if ($previousDriverClass && in_array($previousDriverClass, $result)) {
            $key = array_search($previousDriverClass, $result);
            if ($key !== false) {
                unset($result[$key]);
            }
            array_unshift($result, $previousDriverClass);
        }
        return array_values($result);
    }

    protected function initDrivers()
    {
        $driverClasses   = $this->getDriverClasses();
        foreach ($driverClasses as $driverClass) {
            $this->drivers[$driverClass] = resolve($driverClass);
        }
    }

    protected function getDriverClasses()
    {
        return array_merge($this->getOfdDriverClasses(), $this->getFnsDriverClasses());
    }

    protected function getOfdDriverClasses()
    {
        return config('qr_code.ofd_drivers');
    }

    protected function getFnsDriverClasses()
    {
        return config('qr_code.fns_drivers');
    }


}
