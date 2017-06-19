<?php

namespace ASH\XMLProcessor\XML\Reader\Stream\Provider;

/**
 * Interface representing a stream provider
 */
interface StreamInterface
{
    /**
     * Opens stream for reading
     * @param mixed $source The source for streaming
     * @param int $chunkSize The number of bytes to read
     * @return void
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function open($source, $chunkSize = 1024);

    /**
     * Gets the next chunk form the stream if one is available
     * @return string The next chunk if available, or empty sting if not available
     */
    public function getChunk();

    /**
     * Closes a resource
     * @return void
     */
    public function close();
}