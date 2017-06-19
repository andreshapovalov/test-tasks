<?php

namespace ASH\XMLProcessor\XML\Reader;

use ASH\XMLProcessor\XML\Reader\Parser\ParserInterface;
use ASH\XMLProcessor\XML\Reader\Stream\Provider\StreamInterface;

class XMLReader
{
    /**
     * @var ParserInterface
     */
    protected $parser;

    /**
     * @var StreamInterface
     */
    protected $stream;

    public function __construct(StreamInterface $stream, ParserInterface $parser)
    {
        $this->stream = $stream;
        $this->parser = $parser;
    }

    /**
     * Opens a source for reading
     * @param mixed $source
     * @param int $chunkSize
     * @return void
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function open($source, $chunkSize = 1024)
    {
        $this->stream->open($source, $chunkSize);
    }

    /**
     * Gets the next node from the parser
     * @return string The xml string or empty sting
     */
    public function getNode()
    {
        return $this->parser->getNode($this->stream);
    }

    /**
     * Closes an opened source
     * @return void
     */
    public function close()
    {
        $this->stream->close();
    }
}