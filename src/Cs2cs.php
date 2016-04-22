<?php


namespace SamIT\Proj4;

/**
 * Class Cs2cs
 * This class wraps the cs2cs binary.
 * @package SamIT\Proj4
 */
class Cs2cs
{
    /**
     * @var resource The cs2cs process
     */
    protected $process;
    /**
     * @var stream The pipe used for reading from cs2cs.
     */
    protected $readPipe;
    /**
     * @var stream The pipe used for writing to cs2cs.
     */
    protected $writePipe;

    protected $defaultParameters = [
        "E",
        "f" =>  "'%.15f'"
    ];

    /**
     * @var Closure[] The callbacks for when data is received from cs2cs.
     */
    protected $callbacks = [];
    protected $callbackCounter = 0;

    /**
     * @var int Number of bytes written.
     */
    protected $written;
    /**
     * @var int Number of bytes read.
     */
    protected $read;
    /**
     * @param $parameters
     * @param string $optionPrefix
     * @param string $valueSeparator
     * @return mixed
     */

    protected $buffer;

    protected $active = false;

    protected function arrayToString($parameters, $optionPrefix = '-', $valueSeparator = " ")
    {
        if (is_string($parameters)) {
            return $parameters;
        }

        $result = [];
        foreach($parameters as $key => $value) {
            if (is_int($key)) {
                $result[] = "{$optionPrefix}{$value}";
            } else {
                $result[] = "{$optionPrefix}{$key}{$valueSeparator}{$value}";
            }
        }
        return implode(' ', $result);
    }

    protected function checkCs2csBinary()
    {
        static $exists;
        if (!isset($exists)) {
            exec('which cs2cs', $dummy, $result);
            $exists = $result === 0;
        }

        if (!$exists) {
            throw new \RuntimeException("Cs2cs binary not found.");
        }
    }

    protected function createProcess() {
        $this->process = proc_open($this->cmd, [
            ["pipe", "r"],
            ["pipe", "w"],
        ], $pipes);
        $this->readPipe = $pipes[1];
        $this->writePipe = $pipes[0];
        $this->callbacks = [];
        $this->written = 0;
        $this->callbackCounter = 0;
        $this->buffer = '';
        $this->active = true;
        $this->read = 0;
        stream_set_blocking($pipes[1], false);
    }
    /**
     * Cs2cs constructor.
     * @param array|string $from A Proj4 coordinate system definition.
     * @param array $to A Proj4 coordinate system definition.
     * @param $parameters Parameters for cs2cs.
     */
    public function __construct($from = [], $to = [], array $parameters)
    {
        $this->checkCs2csBinary();
        $parameters = array_merge($this->defaultParameters, $parameters);
        $this->cmd = implode(' ', [
            "cs2cs ",
            $this->arrayToString($parameters),
            $this->arrayToString($from, '+', '='),
            '+test=1232',
            "+to",
            $this->arrayToString($to, '+', '=')
        ]);
    }


    public function transform($x, $y, $callback = null)
    {
        if (!$this->active) {
            $this->createProcess();
        }
        $data = "$x\t$y\t #{$this->callbackCounter}\n";
        $this->callbacks[$this->callbackCounter] = $callback;
        $this->callbackCounter++;
        $this->write($data);
        // Try reading data after writing.
        $this->read();
    }

    /**
     * This should generally not be used.
     * If you use this, it will write data to cs2cs until its buffers are full and the data gets flushed.
     * After that it will return the coordinates.
     * If you only need to transform 1 coordinate during the request, instead use transform() followed by close().
     *
     */
    public function blockingTransform($x, $y)
    {
        if (!$this->active) {
            $this->createProcess();
        }
        $result = null;
        $callback = function($x, $y, $longitude, $latitude) use (&$result) {
            $result = [$longitude, $latitude];
        };
        $this->transform($x, $y, $callback);
        while (!isset($result)) {
            $this->read();
            $this->write("#\n");
        }

        if (preg_match('/(#\n)*(.*)/', $this->buffer, $matches)) {
            $this->buffer = $matches[2];
        }
        return $result;
    }

    /**
     * Attempts to read data from cs2cs, if timeout > 0 then it will block until data is available.
     * @param int $timeout 0
     * @return int The number of bytes read.
     */
    public function read($timeout = 0)
    {
        if (!$this->active) {
            return 0;
        }

        $readBytes = $this->read;
        $bufferLength = strlen($this->buffer);
        $read = [$this->readPipe];
        $dummy = [];
        while (stream_select($read, $dummy, $dummy, $timeout) > 0 && !feof($read[0])) {
            $this->buffer .= stream_get_contents($read[0]);
        }

        $this->read += strlen($this->buffer) - $bufferLength;


        while (preg_match('/(?<x>\d+.\d+)\s+(?<y>\d+.\d+)\s+(?<lon>\d+.\d+)\s+(?<lat>\d+.\d+)\s+(?<h>\d+.\d+)\s*#(?<key>\d+)\n(?<rest>.*)/s', $this->buffer, $matches)) {
            $key = intval($matches['key']);
            if (!isset($this->callbacks[$key])) {
                throw new \RuntimeException("Received data with unknown callback identifier: $key");
            }
            call_user_func(
                $this->callbacks[$key],
                floatval($matches['x']),
                floatval($matches['y']),
                floatval($matches['lon']),
                floatval($matches['lat']),
                floatval($matches['h'])
            );

            unset($this->callbacks[$key]);
            $this->buffer = $matches['rest'];
        }

        return $this->read - $readBytes;
    }

    /**
     * Reads all remaining data and calls the callbacks.
     */
    public function close()
    {
        if ($this->active) {
            // Close the input pipe from cs2cs so it flushes all data.
            fclose($this->writePipe);
            $this->active = false;

            return $this->read(1);
        }
    }

    public function __destruct()
    {
        if ($this->active && proc_get_status($this->process)['running']) {
            $this->close();
            proc_close($this->process);
        }
    }

    public function __clone()
    {
        $this->process = null;
        $this->active = false;

    }

    /**
     * @return int the number of bytes written.
     */
    protected function write($data)
    {
        $written = fwrite($this->writePipe, $data);
        $this->written += $written;
        return $written;
    }


}