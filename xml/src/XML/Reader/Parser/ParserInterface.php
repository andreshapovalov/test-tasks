<?php
namespace ASH\XMLProcessor\XML\Reader\Parser;

use ASH\XMLProcessor\XML\Reader\Stream\Provider\StreamInterface;

/**
 * Interface representing a parser
 */
interface ParserInterface
{
    /**
     * Tries to retrieve the next node
     * @param  StreamInterface $stream The stream source to use
     * @return string The next xml node or empty string if one could not be retrieved
     */
    public function getNode(StreamInterface $stream);
}