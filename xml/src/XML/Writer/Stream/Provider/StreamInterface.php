<?php

namespace ASH\XMLProcessor\XML\Writer\Stream\Provider;

/**
 * Interface representing a stream provider
 */
interface StreamInterface
{
    /**
     * Opens stream for adding
     * @param mixed $target The target for streaming
     * @return void
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function open($target);

    /**
     * Appends data to stream
     * @param $item string
     * @return void
     */
    public function append($item);

    /**
     * Closes an opened resource
     * @return void
     */
    public function close();
}