<?php

namespace ASH\XMLProcessor\XML\Writer\Stream\Provider;

class File implements StreamInterface
{
    /**
     * @var resource
     */
    private $handle;

    /**
     * {@inheritdoc}
     */
    public function open($target)
    {
        if (!$target) {
            throw new \InvalidArgumentException('The target is not specified');
        }

        if (is_string($target)) {
            $this->handle = fopen($target, 'a');
        } else if (is_resource($target) && get_resource_type($target) === 'stream') {
            $this->handle = $target;
        } else {
            throw new \RuntimeException('First argument must be either a filename or a file handle');
        }

        if ($this->handle === false) {
            throw new \RuntimeException("Couldn't read resource");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function append($item)
    {
        fwrite($this->handle, $item);
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }
}