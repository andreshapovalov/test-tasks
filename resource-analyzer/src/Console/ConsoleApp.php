<?php

namespace ASH\ResourceAnalyzer\Console;

use ASH\ResourceAnalyzer\Service\ResourceAnalyzer;
use Exception;

class ConsoleApp
{
    /**
     * Holds parsed options, specified by user
     * @var array
     */
    private $parsedOptions;

    public function __construct()
    {
        set_error_handler(function ($errorNumber, $errorText, $errorFile, $errorLine) {
            throw new \ErrorException($errorText, 0, $errorNumber, $errorFile, $errorLine);
        });
    }

    /**
     * Runs console application
     * @return void
     */
    public function run()
    {
        if ($this->parsedOptions = getopt('s::e::h')) {
            $this->execute();
        } else {
            $this->println('You have to specify at least a source URL(-s), use help(-h) for more details');
        }
    }

    /**
     * Executes commands depending on the specified options
     * @return void
     */
    private function execute()
    {
        if (isset($this->parsedOptions['h'])) {
            $this->printHelp();
        } else if ($source = $this->getOption('s')) {
            try {
                $resourceAnalyzer = new ResourceAnalyzer();
                $excludeResources = $this->getOption('e')??[];

                if (is_string($excludeResources)) {
                    $excludeResources = explode(',', $excludeResources);
                }

                $this->println($resourceAnalyzer->analyze($source, $excludeResources));
            } catch (Exception $e) {
                $this->println($e->getMessage(), true);
            }
        } else {
            $this->println('Undefined command', true);
        }
    }

    /**
     * Prints help info
     */
    private function printHelp()
    {
        $help = <<<HELP
Resource analyser

OPTIONS
    -s  Source URL
    -e  Exclude embedded resources(only for html)
    -h  Help
USAGE
    -s=init
    Returns download size of resource and total number of made queries.
    If it's html, shows number of additional queries, and size of embedded resources 
    
    -s=init -e="js,css"
    Returns download size of resource, without size of js and css
HELP;
        $this->println($help);
    }

    /**
     * Gets an option value by name
     * @param string $name The name of option
     * @return mixed|null Return the option value
     */
    private function getOption($name)
    {
        return $this->parsedOptions[$name]??null;
    }

    /**
     * Prints messages to stream, depending on message type
     * @param string $text The message text
     * @param bool $error Indicates if it's an error message
     * @return void
     */
    public function println($text, $error = false)
    {
        $handle = $error ? STDERR : STDOUT;
        fwrite($handle, $text . PHP_EOL);
    }
}