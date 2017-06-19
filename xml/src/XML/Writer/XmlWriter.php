<?php

namespace ASH\XMLProcessor\XML\Writer;

use ASH\XMLProcessor\XML\Writer\Stream\Provider\StreamInterface;

class XmlWriter
{
    /**
     * @var StreamInterface
     */
    protected $stream;

    public function __construct(StreamInterface $stream)
    {
        $this->stream = $stream;
    }

    /**
     * Opens a target for adding data
     * @param mixed $target
     * @return void
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function open($target)
    {
        $this->stream->open($target);
    }

    /**
     * Appends data to stream
     * @param $item string
     */
    public function append($item)
    {
        $this->stream->append($item);
    }

    /**
     * Closes an opened resource
     * @return void
     */
    public function close()
    {
        $this->stream->close();
    }
}