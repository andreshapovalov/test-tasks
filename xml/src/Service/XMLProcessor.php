<?php

namespace ASH\XMLProcessor\Service;

use ASH\XMLProcessor\DB\Model\Repositories\UserRepository;
use ASH\XMLProcessor\XML\Converter\ConverterInterface;
use ASH\XMLProcessor\XML\Reader\XMLReader;
use ASH\XMLProcessor\XML\Writer\XmlWriter;
use RuntimeException;


/**
 * The class provides methods for working with huge xml files
 */
class XMLProcessor
{
    private $reader;
    private $writer;
    private $converter;
    private $userRepository;

    /**
     * @param XMLReader $reader
     */
    public function setReader(XMLReader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * @return XMLReader
     */
    public function getReader()
    {
        return $this->reader;
    }

    /**
     * @param XmlWriter $writer
     */
    public function setWriter(XmlWriter $writer)
    {
        $this->writer = $writer;
    }

    /**
     * @return XmlWriter
     */
    public function getWriter()
    {
        return $this->writer;
    }

    /**
     * @param ConverterInterface $converter
     */
    public function setConverter(ConverterInterface $converter)
    {
        $this->converter = $converter;
    }

    /**
     * @return ConverterInterface
     */
    public function getConverter()
    {
        return $this->converter;
    }

    /**
     * @param UserRepository $userRepository
     */
    public function setUserRepository($userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @return UserRepository
     */
    public function getUserRepository()
    {
        return $this->userRepository;
    }

    /**
     * Grabs data from xml and saves ones to db
     * @param string $sourceFileName The name of xml file
     * @return int Returns number of found items
     */
    public function import($sourceFileName)
    {
        $reader = $this->getReader();
        $reader->open($sourceFileName);
        $converter = $this->getConverter();
        $userRepository = $this->getUserRepository();

        $batchItemsCount = 0;
        $batchSize = 100;
        $users = [];
        $importedItemsCount = 0;

        while ($node = $reader->getNode()) {
            if ($user = $converter->xmlToArray($node)) {
                $users[] = $user;
                $batchItemsCount++;

                if (0 === $batchItemsCount % $batchSize) {
                    $userRepository->saveMany($users);
                    $users = [];
                    $batchItemsCount = 0;
                }

                $importedItemsCount++;
            }
        }

        if ($users) {
            $userRepository->saveMany($users);
        }

        $users = null;

        return $importedItemsCount;
    }

    /**
     * Filters data by expression
     * @param string $expression The filtering expression
     * @param string $targetFile The target file for results of filtering
     * @return int Returns number of found items
     */
    public function filter($expression, $targetFile)
    {
        $foundItemsCount = 0;

        if (file_exists($targetFile)) {
            unlink($targetFile);
        }

        $writer = $this->getWriter();
        $writer->open($targetFile);
        $converter = $this->getConverter();

        $writer->append('<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL);
        $writer->append('<users>' . PHP_EOL);
        $indent = '    ';

        $criteria = null;

        if ($expression) {
            $criteria = $this->convertFilterExpression($expression);
        } else {
            throw new RuntimeException('Please specify the filter parameters!!!');
        }

        foreach ($this->getUserRepository()->findByCriteria($criteria) as $user) {
            $writer->append($converter->arrayToXML($user, $indent));
            $foundItemsCount++;
        }

        $writer->append('</users>');
        $writer->close();

        return $foundItemsCount;
    }

    /**
     * Converts filtering expression to search criteria
     * @param string $expression The filtering expression
     * @return array Returns search criteria
     */
    public function convertFilterExpression($expression)
    {
        $criteria = null;
        $expressionParts = explode(' ', $expression);

        if (count($expressionParts) >= 3) {
            $availableOperators = [
                '=',
                '!=',
                '<>',
                '>',
                '<',
                '>=',
                '<=',
                '!<',
                '!>',
                'btw'
            ];

            if (!in_array($expressionParts[1], $availableOperators)) {
                throw new RuntimeException("Can't define operator in teh expression");
            }

            $operator = $expressionParts[1];

            $criteria = [
                'field' => $expressionParts[0],
                'operator' => $operator,
                'arguments' => [
                    $expressionParts[2]
                ]
            ];

            if (isset($expressionParts[3])) {
                $criteria['arguments'][] = $expressionParts[3];
            }

            if ($operator === 'btw' && count($criteria['arguments']) !== 2) {
                throw new RuntimeException('Between operation requires two arguments');
            }
        } else {
            throw new RuntimeException("Can't covert the expression");
        }

        return $criteria;
    }

    /**
     * Cleans users table
     * @return void
     */
    public function cleanCachedData()
    {
        $this->getUserRepository()->truncate();
    }
}