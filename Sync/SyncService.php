<?php

namespace App\Services\Sync;

use App\Jobs\MissingSyncsJob;
use App\Models\Device;
use App\Repositories\DeviceRepository;
use App\Repositories\Sync\SyncInterface;
use App\Repositories\Sync\SyncTrait;
use Carbon\Carbon;
use Tochka\JsonRpc\Helpers\ArrayHelper;

class SyncService {

    protected $deviceRepository;

    protected $apiVersion;

    /**
     * @param DeviceRepository $deviceRepository
     */
    public function __construct(DeviceRepository $deviceRepository)
    {
        $this->deviceRepository = $deviceRepository;
    }

    public function setApiVersion($version)
    {
        $this->apiVersion = $version;
    }

    /**
     * @param array $upload
     * @return array
     * @throws \Exception
     */
    public function sync($upload)
    {
        $result = [];
        $upload = ArrayHelper::fromObject($upload);
        foreach ($this->getSyncableRepositories() as $repository) {
            $syncKey = $repository->getSyncKey();

            $result[$syncKey] = $repository->sync($upload[$syncKey] ?? []);
        }
        $this->queueMissingSyncs();

        return $result;
    }

    /**
     * @param $list
     * @return array
     * @throws \Exception
     */
    public function confirm($list)
    {
        $result = [];
        $list = ArrayHelper::fromObject($list);
        foreach ($this->getSyncableRepositories() as $repository) {
            $syncKey = $repository->getSyncKey();
            if (!isset($list[$syncKey])) {
                continue;
            }
            $result[$syncKey] = $repository->confirm($list[$syncKey]);
        }
        return $result;
    }

    /**
     * @param Device $device
     * @param string $deviceMissingSyncsAt
     * @throws \Exception
     */
    public function createMissingSyncs(Device $device, $deviceMissingSyncsAt)
    {
        $this->deviceRepository->setCurrentDevice($device);
        foreach ($this->getSyncableRepositories() as $repository) {
            $repository->createMissingSyncs($deviceMissingSyncsAt); // Создать синхи для недавно измененных объектов
        }

        $this->deviceRepository->setCurrentDeviceMissingSyncsAt($device->missing_syncs_job_at ?: Carbon::now()->toString());
        $this->deviceRepository->removeCurrentDeviceMissingSyncsJobAt();
    }

    /**
     * @return SyncTrait[]
     */
    protected function getSyncableRepositories()
    {
        $result = [];
        foreach ($this->getSyncableRepositoryClasses() as $syncableRepositoryClass) {
            $result[$syncableRepositoryClass] = resolve($syncableRepositoryClass);
        }
        return $result;
    }

    protected function getSyncableRepositoryClasses()
    {
        $repositories = config('sync.repositories');
        for ($apiVersion = $this->apiVersion; $apiVersion > 1; $apiVersion --) {
            if (isset($repositories[$apiVersion])) {
                return $repositories[$apiVersion];
            }
        }
        return $repositories[1];
    }


    /**
     * @throws \Exception
     */
    protected function queueMissingSyncs()
    {
        $checkNeedMissingSyncs = $this->checkNeedMissingSyncs();
        if ($checkNeedMissingSyncs === false) {
            return;
        }

        $device = $this->deviceRepository->getCurrentDevice();
        MissingSyncsJob::dispatch($device, $device->missing_syncs_at, $this->apiVersion, $checkNeedMissingSyncs)->onQueue('missing_syncs');

        $this->deviceRepository->setCurrentDeviceMissingSyncsJobAt();
    }

    /**
     * @return bool
     * @throws \Exception
     */
    protected function checkNeedMissingSyncs() {
        $device = $this->deviceRepository->getCurrentDevice();
        if ($device->missing_syncs_job_at && (Carbon::now()->timestamp - Carbon::parse($device->missing_syncs_job_at)->timestamp > (2 * 3600))) {
            $this->deviceRepository->removeCurrentDeviceMissingSyncsJobAt();
        }
        if ($device->missing_syncs_job_at) {
            return false;
        }
        if (config('app.debug') == true) {
            return true;
        }
        $curStamp = Carbon::now()->timestamp;
        $lastStamp = $device->missing_syncs_at ? Carbon::parse($device->missing_syncs_at)->timestamp : 0;

        // Если давно не проверял
        if ($curStamp - $lastStamp > SyncInterface::SYNC_MISSING_TIME) {
            return true;
        }

        // Первое время после логина если прочее не выполнено
        if (!$device->user->is_anonymous) {
            $loginStamp = Carbon::parse($device->user->last_login_at)->timestamp;
            $loginDiff = $curStamp - $loginStamp;
            if (($loginDiff < SyncInterface::SYNC_MISSING_TIME) && $loginDiff > SyncInterface::SYNC_MIN_LOGIN_DIFF) {
                return true;
            }
        }

        // Если удачно зашел
        if ($curStamp % SyncInterface::SYNC_MISSING_MOD == 0) {
            return true;
        }

        return false;
    }

}
