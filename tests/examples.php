<?php
define('ABSPATH', __DIR__);
require __DIR__ . '/../tms-shiptimize-fix/vendor/autoload.php';
$util = TMSVendor\Phone\PhoneNumberUtil::getInstance();
foreach (array('GB','PT','US','IT') as $region) {
    $example = $util->getExampleNumberForType($region, TMSVendor\Phone\PhoneNumberType::MOBILE);
    echo $region . ' ' . $util->format($example, TMSVendor\Phone\PhoneNumberFormat::E164) . PHP_EOL;
}
