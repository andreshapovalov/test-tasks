<?php

namespace ASH\ResourceAnalyzer\Service;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use RuntimeException;

/**
 * The class allows to analyze a remote resource
 */
class ResourceAnalyzer
{
    /**
     * Holds the analyzer's configuration
     * @var array
     */
    private $options;

    /**
     * The embedded resources, which have to be excluded from analysis
     * @var array
     */
    private $excludeResources = [];

    /**
     * The mime type of source
     * @var string
     */
    private $sourceContentType = '';

    /**
     * The total number of made requests
     * @var int
     */
    private $totalRequestsCount = 0;

    /**
     * The total number of made requests
     * @var int
     */
    private $embeddedResourcesRequestsCount = 0;

    /**
     * The download size of source
     * @var int
     */
    private $downloadSize = 0;


    /**
     * ResourceAnalyzer constructor.
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'output_format' => 'string'
        ], $options);
    }

    /**
     * Increases total number of made requests
     * @param int $value The number of requests
     * @return void
     */
    private function increaseTotalRequestsCount($value = 1)
    {
        $this->totalRequestsCount += $value;
    }

    /**
     * Increases a total number of made requests for embedded resources
     * @param int $value The number of requests
     * @return void
     */
    private function increaseEmbeddedResourcesRequestsCount($value = 1)
    {
        $this->embeddedResourcesRequestsCount += $value;
    }

    /**
     * Increases a total value of download size
     * @param int $value The number of bytes
     * @return void
     */
    private function increaseDownloadSize($value)
    {
        $this->downloadSize += $value;
    }

    /**
     * Calculates download size of the source
     * @param string $url The source url
     * @param array $excludeResources The embedded resources, which have to be excluded from analysis
     * @return mixed Returns result of the analysis
     * @throws InvalidArgumentException|RuntimeException
     */
    public function analyze($url, array $excludeResources = [])
    {
        if (!function_exists('curl_version')) {
            throw new RuntimeException('The ResourceAnalyzer requires cURL, please turn on the extension');
        }

        if (!$url || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw  new InvalidArgumentException('Invalid URL');
        }

        if (!($rawResponse = $this->sendRequest($url))) {
            throw new RuntimeException("Can't get resource");
        }
        $this->excludeResources = $excludeResources;

        $responseMessage = $this->parseResponseMessage($rawResponse);

        if ($responseMessage['code'] === 200) {
            if (!($this->sourceContentType = $this->getContentType($responseMessage))) {
                throw new RuntimeException("Can't define a content type");
            }

            if ($contentLength = $this->getContentLength($responseMessage)) {
                $this->increaseDownloadSize($contentLength);
            }

            if ($this->sourceContentType === 'text/html') {
                if ($html = $this->sendRequest($url, true)) {
                    if (!$contentLength) {
                        $this->increaseDownloadSize(strlen($html));
                    }
                    $this->processPageResourcesDetails($html, $url);
                }
            }
        } else {
            throw new RuntimeException(sprintf("Can't analyze the URL, code: %s, text: %s", $rawResponse['code'], $rawResponse['text']));
        }

        return $this->prepareResult();
    }

    /**
     * Initializes a single cURL session
     * @param string $url The source url
     * @param bool $contentOnly If true you'll get content of the url without headers, otherwise only headers will be returned
     * @return resource Returns cURL's handle
     */
    private function initChanel($url, $contentOnly = false)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_URL, $url);

        if (!$contentOnly) {
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }

        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36'
        ]);

        return $ch;
    }

    /**
     * Sends single request
     * @param string $url The source url
     * @param bool $contentOnly If true you'll get content of the url without headers, otherwise only headers will be returned
     * @return mixed Returns requested data
     */
    private function sendRequest($url, $contentOnly = false)
    {
        $this->increaseTotalRequestsCount();

        $ch = $this->initChanel($url, $contentOnly);
        $data = curl_exec($ch);
        curl_close($ch);

        return $data;
    }

    /**
     * Sends multiple requests in parallel
     * @param array $urls Links to sources
     * @return array Returns requested content
     */
    private function sendRequests($urls)
    {
        $this->increaseTotalRequestsCount(count($urls));

        // Create get requests for each URL
        $multiHandle = curl_multi_init();
        $channels = [];

        foreach ($urls as $i => $url) {
            $channelHandle = $this->initChanel($url);
            curl_multi_add_handle($multiHandle, $channelHandle);
            $channels[$i] = $channelHandle;
        }

        $active = false;
        do {
            $status = curl_multi_exec($multiHandle, $active);
        } while ($active && $status == CURLM_OK);

        $responses = [];

        foreach ($channels as $channel) {
            $responses[] = curl_multi_getcontent($channel);
            curl_multi_remove_handle($multiHandle, $channel);
            curl_close($channel);
        }

        curl_multi_close($multiHandle);

        return $responses;
    }

    /**
     * Converts a response message to array
     * @param string $responseMessage
     * @return array
     */
    private function parseResponseMessage($responseMessage)
    {
        $result = [];
        $lines = explode("\r\n", $responseMessage);

        if ($lines && !empty($lines[0])) {
            // handle status line
            $statusLine = explode(' ', array_shift($lines), 3);
            $version = explode('/', $statusLine[0]);
            $result['version'] = (float)$version[1];
            $result['code'] = (int)$statusLine[1];
            $result['text'] = $statusLine[2];

            // parse headers
            $result['headers'] = [];
            while ($headersLine = trim(array_shift($lines))) {
                list($name, $value) = explode(':', $headersLine, 2);
                $name = strtolower(trim($name));
                if (isset($result['headers'][$name])) {
                    $result['headers'][$name] = (array)$result['headers'][$name];
                    $result['headers'][$name][] = trim($value);
                } else {
                    $result['headers'][$name] = trim($value);
                }
            }
        }
        return $result;
    }

    /**
     * Gets a mime type from the response message
     * @param array $responseMessage It's a parsed response message
     * @return string It's a mime type
     */
    private function getContentType($responseMessage)
    {
        $contentType = '';
        if (isset($responseMessage['headers']['content-type'])) {
            preg_match('/[a-z-]+\/[a-z-]+/', $responseMessage['headers']['content-type'], $matches);
            if ($matches) {
                $contentType = $matches[0];
            }
        }
        return $contentType;
    }

    /**
     * Gets a length of source's body from the response message
     * @param array $responseMessage It's a parsed response message
     * @return int Returns body length
     */
    private function getContentLength($responseMessage)
    {
        $contentLength = 0;

        if (isset($responseMessage['headers']['content-length'])) {
            $contentLength = intval($responseMessage['headers']['content-length']);
        }

        return $contentLength;
    }

    /**
     * Analyses a download size of embedded resources
     * @param string $html It's a source page
     * @param string $sourceURL The link to source
     * @return void
     */
    private function processPageResourcesDetails($html, $sourceURL)
    {
        $resourcesLinks = $this->getLinks($html);

        if ($resourcesLinks) {
            $resourcesLinks = $this->prepareURLs($resourcesLinks, $sourceURL);

            $this->increaseEmbeddedResourcesRequestsCount(count($resourcesLinks));
            $rawResponseMessages = $this->sendRequests($resourcesLinks);

            foreach ($rawResponseMessages as $rawResponseMessage) {
                $responseMessage = $this->parseResponseMessage($rawResponseMessage);

                if ($responseMessage && $responseMessage['code'] === 200) {
                    $this->increaseDownloadSize($this->getContentLength($responseMessage));
                }
            }
        }
    }

    /**
     * Grabs links to embedded resources
     * @param string $html It's a html page content
     * @return array URLs of embedded resources
     */
    private function getLinks($html)
    {
        $doc = new DOMDocument();
        $libxmlPreviousState = libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($libxmlPreviousState);

        $xpath = new DOMXPath($doc);

        $resourceGroups = [
            'css' => '//link[@type="text/css" or @rel="stylesheet"]',
            'js' => '//script',
            'img' => '//img|//link[contains(@rel, "icon")]',
            'embed' => '//embed'
        ];

        if ($this->excludeResources) {
            $filteredResourceGroups = array_filter($resourceGroups, function ($key) {
                return !in_array($key, $this->excludeResources);
            }, ARRAY_FILTER_USE_KEY);
        } else {
            $filteredResourceGroups = $resourceGroups;
        }

        $externalResources = [];

        if ($filteredResourceGroups) {
            $query = implode('|', $filteredResourceGroups);
            $links = $xpath->query($query);

            foreach ($links as $element) {
                /** @var DOMElement $element */
                $tagName = $element->tagName;

                if ($tagName === 'link') {
                    $link = $element->getAttribute('href');
                } else {
                    $link = $element->getAttribute('src');
                }

                if ($link) {
                    $externalResources[] = $link;
                }
            }
        }

        return $externalResources;
    }

    /**
     * Checks if the url is absolute
     * @param string $url
     * @return bool
     */
    private function isAbsolute($url)
    {
        $pattern = "/^(?:ftp|https?|feed):\/\/(?:(?:(?:[\w\.\-\+!$&'\(\)*\+,;=]|%[0-9a-f]{2})+:)*
    (?:[\w\.\-\+%!$&'\(\)*\+,;=]|%[0-9a-f]{2})+@)?(?:
    (?:[a-z0-9\-\.]|%[0-9a-f]{2})+|(?:\[(?:[0-9a-f]{0,4}:)*(?:[0-9a-f]{0,4})\]))(?::[0-9]+)?(?:[\/|\?]
    (?:[\w#!:\.\?\+=&@$'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})*)?$/xi";

        return (bool)preg_match($pattern, $url);
    }

    /**
     * Prepares embedded resources links for checking
     * @param array $urls Links to embedded resources
     * @param string $parentURL It's a source URL
     * @return array Returns prepared urls
     */
    private function prepareURLs($urls, $parentURL)
    {
        $absoluteURLs = [];
        $urlParts = parse_url($parentURL);
        $baseURL = $urlParts['scheme'] . '://' . $urlParts['host'];

        if ($urls && $parentURL) {
            $urls = array_unique($urls);

            foreach ($urls as $url) {
                if ($this->isAbsolute($url)) {
                    $absoluteURLs[] = $url;
                } else {
                    $absoluteURLs[] = $this->makeAbsoluteURL($url, $baseURL);
                }
            }
        }

        return $absoluteURLs;
    }

    /**
     * Converts relative url to absolute
     * @param string $relativeURL It's a relative url from html page of source
     * @param string $baseURL It's a base part of source url
     * @return string Returns absolute url
     */
    private function makeAbsoluteURL($relativeURL, $baseURL)
    {
        if ($relativeURL[0] === '/' && $relativeURL[1] === '/') {
            $url = 'https:' . $relativeURL;
        } else {
            $url = $baseURL . $relativeURL;
        }

        return $url;
    }

    /**
     * Formats a download size
     * @param int $size The number of bytes
     * @return string Returns formatted sting
     */
    private function formatDownloadSize($size)
    {
        if ($size >= 1073741824) {
            $size = number_format($size / 1073741824, 2) . ' GB';
        } elseif ($size >= 1048576) {
            $size = number_format($size / 1048576, 2) . ' MB';
        } elseif ($size >= 1024) {
            $size = number_format($size / 1024, 2) . ' KB';
        } elseif ($size > 1) {
            $size = $size . ' bytes';
        } elseif ($size == 1) {
            $size = $size . ' byte';
        } else {
            $size = '0 bytes';
        }

        return $size;
    }

    /**
     * Prepares the result of the source's analysis
     * @return array|string Returns formatted result of the analysis
     */
    private function prepareResult()
    {
        if ($this->options['output_format'] === 'string') {
            if ($this->sourceContentType === 'text/html') {
                $excludedResources = '';
                if ($this->excludeResources) {
                    $excludedResources = ', excluded: ' . implode(', ', $this->excludeResources);
                }

                $result = sprintf('Total requests count: %d, embedded resources requests count: %d, download size: %s%s', $this->totalRequestsCount, $this->embeddedResourcesRequestsCount, $this->formatDownloadSize($this->downloadSize), $excludedResources);
            } else {
                $result = sprintf('Requests count: %d, download size: %s', $this->totalRequestsCount, $this->formatDownloadSize($this->downloadSize));
            }
        } else {
            $result = [
                'total_requests_count' => $this->totalRequestsCount,
                'embedded_resources_requests_count' => $this->embeddedResourcesRequestsCount,
                'download_size' => $this->downloadSize,
                'excluded_resources' => $this->excludeResources
            ];
        }

        return $result;
    }
}