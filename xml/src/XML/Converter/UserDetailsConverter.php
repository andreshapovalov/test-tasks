<?php

namespace ASH\XMLProcessor\XML\Converter;

class UserDetailsConverter implements ConverterInterface
{
    /**
     * {@inheritdoc}
     */
    public function xmlToArray($xml)
    {
        $userDetails = null;
        //TODO: the simple and not flexible solution must be replaced by recursive parser, but for this simple structure one's just working
        $xml = preg_replace('/(\>)\s*(\<)/m', '$1$2', $xml);
        $rawUserDetails = $this->getContentBetweenTags($xml, 'user');

        if ($rawUserDetails) {
            $userDetails = [
                'id' => $this->getContentBetweenTags($rawUserDetails, 'id'),
                'name' => $this->getContentBetweenTags($rawUserDetails, 'name'),
                'email' => $this->getContentBetweenTags($rawUserDetails, 'email'),
                'age' => $this->getContentBetweenTags($rawUserDetails, 'age')
            ];
        }

        return $userDetails;
    }

    /**
     * Grabs content between tags
     * @param string $xml The source $xml
     * @param string $tagName Desired tag
     * @return string Returns content between tags
     */
    private function getContentBetweenTags($xml, $tagName)
    {
        $text = '';
        $pattern = "/<$tagName>(.*)<\/$tagName>/";

        if (preg_match($pattern, $xml, $matches)) {
            if (isset($matches[1])) {
                $text = $matches[1];
            }
        }

        return $text;
    }

    /**
     * {@inheritdoc}
     */
    public function arrayToXML(array $user, $indent = '')
    {
        if (!empty($indent)) {
            $endOfLine = PHP_EOL;
        } else {
            $endOfLine = '';
        }

        $xml = $indent . '<user>' . $endOfLine;

        foreach ($user as $key => $value) {
            $xml .= "{$indent}{$indent}<{$key}>{$value}</{$key}>{$endOfLine}";
        }

        $xml .= $indent . '</user>' . $endOfLine;

        return $xml;
    }
}