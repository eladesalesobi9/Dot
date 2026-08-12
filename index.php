<?php
declare(strict_types=1);

ignore_user_abort(true);

$pathPrefix = '';

require "{$pathPrefix}vendor/autoload.php";

use Dotenv\Dotenv;
use App\Exception\ApiCallException;
use App\FileCache;
use App\Api;
use App\Config;
use App\TooManyRequestsWaiter;

$envFilename = empty($argv[1]) ? '.env' : $argv[1];
$dotenv = Dotenv::createUnsafeImmutable(__DIR__, $envFilename);
$dotenv->safeLoad();

$config = new Config(
    getenv('REGION'),
    getenv('USER_ID'),
    getenv('TENANCY_ID'),
    getenv('KEY_FINGERPRINT'),
    getenv('PRIVATE_KEY_FILENAME'),
    getenv('AVAILABILITY_DOMAIN') ?: null,
    getenv('SUBNET_ID'),
    getenv('IMAGE_ID'),
    (int) getenv('OCPUS'),
    (int) getenv('MEMORY_IN_GBS')
);

$bootVolumeSizeInGBs = (string) getenv('BOOT_VOLUME_SIZE_IN_GBS');
$bootVolumeId = (string) getenv('BOOT_VOLUME_ID');
if ($bootVolumeSizeInGBs) {
    $config->setBootVolumeSizeInGBs($bootVolumeSizeInGBs);
} elseif ($bootVolumeId) {
    $config->setBootVolumeId($bootVolumeId);
}

$api = new Api();
if (getenv('CACHE_DOMAINS')) {
    $api->setCache(new FileCache($config));
}
if (getenv('RATE_LIMIT_WAIT')) {
    $api->setWaiter(new TooManyRequestsWaiter((int) getenv('RATE_LIMIT_WAIT')));
}
$notifier = (function (): \App\Interfaces\NotifierInterface {
    return new \App\Notification\Telegram();
})();

$shape = getenv('SHAPE');

$maxRunningInstancesOfThatShape = 1;
if (getenv('MAX_INSTANCES') !== false) {
    $maxRunningInstancesOfThatShape = (int) getenv('MAX_INSTANCES');
}

$instances = $api->getInstances($config);

$existingInstances = $api->checkExistingInstances($config, $instances, $shape, $maxRunningInstancesOfThatShape);
if ($existingInstances) {
    echo "$existingInstances\n";
    return;
}

if (!empty($config->availabilityDomains)) {
    if (is_array($config->availabilityDomains)) {
        $availabilityDomains = $config->availabilityDomains;
    } else {
        $availabilityDomains = [ $config->availabilityDomains ];
    }
} else {
    $availabilityDomains = $api->getAvailabilityDomains($config);
}

foreach ($availabilityDomains as $availabilityDomainEntity) {
    $availabilityDomain = is_array($availabilityDomainEntity) ? $availabilityDomainEntity['name'] : $availabilityDomainEntity;
    try {
        $instanceDetails = $api->createInstance($config, $shape, getenv('SSH_PUBLIC_KEY'), $availabilityDomain);
    } catch(ApiCallException $e) {
        $message = $e->getMessage();
        echo "$message\n";

        if (
            $e->getCode() === 500 &&
            strpos($message, 'InternalError') !== false &&
            strpos($message, 'Out of host capacity') !== false
        ) {
            sleep(5);
            continue;
        }

        return;
    }

    $message = json_encode($instanceDetails, JSON_PRETTY_PRINT);
    echo "$message\n";
    if ($notifier->isSupported()) {
        $notifier->notify($message);
    }

    return;
}
