<?php
include 'src/Cs2cs.php';
$cs2cs = new SamIT\Proj4\Cs2cs([
    'proj' => 'sterea',
    'lat_0' => 52.15616055555555,
    'lon_0' => 5.38763888888889,
    'k' => 0.9999079,
    'x_0' => 155000,
    'y_0' => 463000,
    'ellps' => 'bessel',
    'towgs84' => implode(',', [565.417,50.3319,465.552,-0.398957,0.343988,-1.8774,4.0725]),
    'no_defs',
], [
    'proj' => 'longlat',
    'datum' => 'WGS84',
    'no_defs'
], []);


$x = 236296.709;
$y = 590744.631;

$runs = [];
$runSize = 100;
$runCount = 50;

for($i = 0; $i < $runCount; $i++) {
    $start = microtime(true);
    for ($j = 0; $j < $runSize; $j++) {
        list($longitude, $latitude) = $cs2cs->blockingTransform($x, $y);
    }
    $runs[] = microtime(true) - $start;

    echo '.';
}

print_r([
    'Runcount' => $runCount,
    'Runsize' => $runSize,
    'Blocking' => true,
    'Average:' => array_sum($runs) / count($runs),
    'Minimum:' => min($runs),
    'Maximum:' => max($runs),
]);
$runs = [];
$runSize = 10000;
$count = 0;
for($i = 0; $i < $runCount; $i++) {
    $start = microtime(true);
    $cs2cs = clone $cs2cs;
    for ($j = 0; $j < $runSize; $j++) {
        $cs2cs->transform($x, $y, function($x, $y, $lon, $lat) use (&$count) {
            $count++;
//            echo '+';$runCount
        });
//        echo ".";
    }
    $runs[] = microtime(true) - $start;
    $cs2cs->close();


    echo '.';

}

print_r([
    'Runcount' => $runCount,
    'Runsize' => $runSize,
    'Blocking' => false,
    'Average:' => array_sum($runs) / count($runs),
    'Minimum:' => min($runs),
    'Maximum:' => max($runs),
]);