# cs2cs-php
Coordinate transformation via PHP and cs2cs

# Usage
````
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


$cs2cs->transform($x, $y, function($x, $y, $lon, $lat) use (&$count) {
    $count++;
});
$cs2cs->close();
````

# Explanation
Cs2cs uses output buffering, instead of writing a line immediately after reading a line from stdin, it waits until its output buffer is full and gets flushed automatically.

To efficiently transform many coordinates, we instead pass a callback that gets called with the result when it becomes available.

# Short function doc:
````
transform($x, $y, $callback)
````
Sets up a callback that gets called when the transformation of `$x` and `$y` is complete.

````
close()
````
Terminates the cs2cs process, reads all remaining data. This will call all remaining callbacks.

````
read($timeout = 0)
````
Attempts to read data from cs2cs and fires the appropriate callbacks. Mostly used internally.
If a timeout is set it will block until data becomes available.
Using this can cause deadlocks.
````
blockingTransform($x, $y)
````
If you really need to do the transformation synchronously you can use this.
This will write dummy data to cs2cs and force it to flush its buffers.
If you just need to transform a single coordinate use `transform()` followed by `flush()` instead.
Using this effectively writes 4000 bytes instead of ~25. This is ~10000x slower then doing it asynchronously.

# Cloning
In case you want to do this even faster (not tested!) the object supports cloning.
When a cs2cs object is cloned it creates a new cs2cs process.

# Benchmark, blocking vs async.
Synchronous, note the run size of 100.
````
[Runcount] => 50
[Runsize] => 100
[Blocking] => 1
[Average:] => 1.0074578237534
[Minimum:] => 0.94923281669617
[Maximum:] => 1.6763138771057
````
Asynchronous, note the runsize of 10000.

````
[Runcount] => 50
[Runsize] => 10000
[Blocking] =>
[Average:] => 0.19135624885559
[Minimum:] => 0.17859697341919
[Maximum:] => 0.20333218574524
````

Conclusion: go async.
