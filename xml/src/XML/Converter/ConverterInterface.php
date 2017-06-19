<?php

namespace ASH\XMLProcessor\XML\Converter;

/**
 * Interface representing a data converter
 */
interface ConverterInterface
{
    /**
     * Converts a xml string to array
     * @param string $xml The source string
     * @return array
     * @throws \InvalidArgumentException
     */
    public function xmlToArray($xml);

    /**
     * Converts an array to xml string
     * @param array $data The source array
     * @param string $indent
     * @return string
     * @throws \InvalidArgumentException
     */
    public function arrayToXML(array $data, $indent = '');
}