<?php

namespace ByteBuffer;

class Stream
{
    public $options = [];

    public $isLittleEndian = true;

    protected $_handle;

    protected function __construct($stream, $options = [])
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Stream must be a resource');
        }
        $this->_handle = $stream;
        $this->options = $options;
    }

    public static function factory($resource = '', $options = [])
    {
        $type = gettype($resource);
        switch ($type) {
            case 'string':
                $stream = fopen('php://temp', 'r+');
                if ($resource !== '') {
                    fwrite($stream, $resource);
                    fseek($stream, 0);
                }
                return new static($stream, $options);
            case 'resource':
                return new static($resource, $options);
            case 'object':
                if (method_exists($resource, '__toString')) {
                    return static::factory((string)$resource, $options);
                }
        }
        throw new \InvalidArgumentException(sprintf('Invalid resource type: %s', $type));
    }

    public function rewind()
    {
        return rewind($this->_handle);
    }

    public function write($data, $length = null)
    {
        if ($length === null) {
            return fwrite($this->_handle, $data);
        } else {
            return fwrite($this->_handle, $data, $length);
        }
    }

    public function writeBytes($bytes)
    {
        array_unshift($bytes, 'V*');

        return $this->write(call_user_func_array('pack', $bytes));
    }

    public function writeNull($length)
    {
        return $this->write(pack('x' . $length));
    }

    public function save($file)
    {
        $this->rewind();
        return file_put_contents($file, $this->_handle);
    }

    public function close()
    {
        if (is_resource($this->_handle)) {
            fclose($this->_handle);
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
