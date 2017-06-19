<?php

namespace ASH\XMLProcessor\Util;

use XMLWriter;

/**
 * It's an util class for generating input data
 */
class DataGenerator
{
    /**
     * Creates xml file, fill up it with users
     * @param string $outputFileName The name of target file
     * @param int $itemsCount The number of items that will be generated
     * @param bool $prettify Format out data or not
     * @return void
     */
    public static function generateSourceFile($outputFileName, $itemsCount = 250, $prettify = false)
    {
        $bunchSize = 100;
        $indentString = '    ';
        $start = microtime(true);

        if (file_exists($outputFileName)) {
            unlink($outputFileName);
        }

        $fp = fopen($outputFileName, "a");

        $xmlWriter = new XMLWriter();
        $xmlWriter->openMemory();
        $xmlWriter->startDocument('1.0', 'UTF-8');
        $xmlWriter->setIndent($prettify);
        $xmlWriter->setIndentString($indentString);
        $xmlWriter->startElement('users');
        $xmlWriter->setIndentString($indentString);

        for ($i = 0; $i < $itemsCount; $i++) {
            $userID = $i + 1;
            $xmlWriter->startElement('user');
            self::addSubElement($xmlWriter, $indentString, 'id', $userID);
            self::addSubElement($xmlWriter, $indentString, 'name', self::generateName());
            self::addSubElement($xmlWriter, $indentString, 'email', 'user' . $userID . '@mail.com');
            self::addSubElement($xmlWriter, $indentString, 'age', mt_rand(18, 50));
            $xmlWriter->endElement();
            $xmlWriter->setIndentString($indentString);

            if ($bunchSize > $itemsCount && 0 === $i % $bunchSize) {
                fwrite($fp, $xmlWriter->flush(true));
            }
        }

        $xmlWriter->endElement();
        fwrite($fp, $xmlWriter->flush(true));
        fclose($fp);
    }


    /**
     * Ads sub element
     * @param XMLWriter $xmlWriter
     * @param $indentString
     * @param string $name The element name
     * @param string $value The element value
     * @return void
     */
    private static function addSubElement(XMLWriter $xmlWriter, $indentString, $name, $value)
    {
        $xmlWriter->setIndentString($indentString);
        $xmlWriter->writeElement($name, $value);
    }

    /**
     * Generates random user name
     * @return string Returns user name
     */
    private static function generateName()
    {
        $names = [
            'Christopher',
            'Ryan',
            'Ethan',
            'John',
            'Zoey',
            'Sarah',
            'Michelle',
            'Samantha',
        ];

        $surnames = [
            'Walker',
            'Thompson',
            'Anderson',
            'Johnson',
            'Tremblay',
            'Peltier',
            'Cunningham',
            'Simpson',
            'Mercado',
            'Sellers'
        ];

        $randomName = $names[mt_rand(0, count($names) - 1)];
        $randomSurname = $surnames[mt_rand(0, count($surnames) - 1)];

        return $randomName . ' ' . $randomSurname;
    }
}