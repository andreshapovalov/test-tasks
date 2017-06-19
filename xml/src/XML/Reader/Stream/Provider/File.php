<?php

namespace ASH\XMLProcessor\XML\Reader\Stream\Provider;

class File implements StreamInterface
{
    /**
     * @var resource
     */
    private $handle;

    /**
     * @var int
     */
    private $chunkSize;

    /**
     * {@inheritdoc}
     */
    public function open($source, $chunkSize = 1024)
    {
        if (!$source) {
            throw new \InvalidArgumentException('The target is not specified');
        }

        if (is_string($source)) {
            $this->handle = fopen($source, 'rb');
        } else if (is_resource($source) && get_resource_type($source) === 'stream') {
            $this->handle = $source;
        } else {
            throw new \RuntimeException('First argument must be either a filename or a file handle');
        }

        if ($this->handle === false) {
            throw new \RuntimeException("Couldn't read resource");
        }

        $this->chunkSize = $chunkSize;
    }

    /**
     * {@inheritdoc}
     */
    public function getChunk()
    {
        $buffer = '';

        if (is_resource($this->handle)){
            if(!feof($this->handle)) {
                $buffer = fread($this->handle, $this->chunkSize);
            }else{
                $this->close();
            }
        }

        return $buffer;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        fclose($this->handle);
    }
}