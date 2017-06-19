<?php

namespace ASH\XMLProcessor\XML\Reader\Parser;

use ASH\XMLProcessor\XML\Reader\Stream\Provider\StreamInterface;

class ChunkParser implements ParserInterface
{
    /**
     * Holds the parser configuration
     * @var array
     */
    protected $options;

    /**
     * The latest chunk from the stream
     * @var string
     */
    protected $chunk = '';

    /**
     * Whether to capture or not
     * @var boolean
     */
    protected $capture = false;

    /**
     * The depth of traveled path
     * @var string
     */
    protected $currentDepth = 0;

    /**
     * XML node in making
     * @var string
     */
    protected $currentNode = null;

    /**
     * Parser constructor
     * @param array $options An options array
     */
    public function __construct(array $options = array())
    {
        $this->options = array_merge([
            'capture_depth' => 2
        ], $options);
    }

    /**
     * Cut off the next element from the chunk
     * @return array Either a cut off element array or empty array if one could not be obtained
     */
    protected function cut()
    {
        $cutOfElementDetails = [];
        preg_match('/<[^>]+>/', $this->chunk, $matches, PREG_OFFSET_CAPTURE);

        if (isset($matches[0], $matches[0][0], $matches[0][1])) {
            list($captured, $offset) = $matches[0];

            // data in between
            $data = substr($this->chunk, 0, $offset);

            // cut from chunk
            $this->chunk = substr($this->chunk, $offset + strlen($captured));

            $cutOfElementDetails = [$captured, $data . $captured];
        }

        return $cutOfElementDetails;
    }

    /**
     * Defines offset for edges
     * @param  string $element XML element
     * @return int offset for edges
     */
    protected function getEdgesOffset($element)
    {
        $edges = [
            [
                'opening' => '<?',
                'closing' => '?>',
                'offset' => 0
            ],
            [
                'opening' => '<!--',
                'closing' => '-->',
                'offset' => 0
            ],
            [
                'opening' => '<![CDATA[',
                'closing' => ']]>',
                'offset' => 0
            ],
            [
                'opening' => '<!',
                'closing' => '>',
                'offset' => 0
            ],
            [
                'opening' => '</',
                'closing' => '>',
                'offset' => -1
            ],
            [
                'opening' => '<',
                'closing' => '/>',
                'offset' => 0
            ],
            [
                'opening' => '<',
                'closing' => '>',
                'offset' => 1
            ],
        ];
        $offset = 0;

        foreach ($edges as $edge) {
            if (substr($element, 0, strlen($edge['opening'])) === $edge['opening']
                && substr($element, -1 * strlen($edge['closing'])) === $edge['closing']
            ) {
                $offset = $edge['offset'];
                break;
            }
        }

        return $offset;
    }

    /**
     * The method must be able to request more data even though there isn't any more to fetch from the stream,
     * it wraps the getChunk call, so that it returns not empty string as long as there is XML data left
     * @param  StreamInterface $stream The stream to read from
     * @return bool Returns whether there is more XML data or not
     */
    protected function processChunk(StreamInterface $stream)
    {
        if (strlen($this->chunk) && is_null($this->currentNode)) {
            //continue to process left part of the chunk
            $this->currentNode = '';
            return true;
        } else if (is_null($this->currentNode)) {
            $this->currentNode = '';
        }

        $nextChunk = $stream->getChunk();

        if ($nextChunk) {
            $this->chunk .= $nextChunk;
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getNode(StreamInterface $stream)
    {
        // Iterate and append to $this->chunk
        while ($this->processChunk($stream)) {
            while ($elementDetails = $this->cut()) {
                list($element, $data) = $elementDetails;
                $edgesOffset = $this->getEdgesOffset($element);
                $this->currentDepth += $edgesOffset;
                $flush = false;

                if ($this->currentDepth === $this->options['capture_depth'] && $edgesOffset > 0) {
                    $this->capture = true;
                } else if ($this->currentDepth === $this->options['capture_depth'] - 1 && $edgesOffset < 0) {
                    $flush = true;
                    $this->capture = false;
                }

                if ($this->capture || $flush) {
                    $this->currentNode .= $data;
                }

                if ($flush) {
                    $nodeCopy = $this->currentNode;
                    $this->currentNode = null;
                    return $nodeCopy;
                }
            }
        }

        return '';
    }
}