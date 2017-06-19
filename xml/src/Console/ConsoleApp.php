<?php

namespace ASH\XMLProcessor\Console;

use ASH\XMLProcessor\DB\DatabaseManager;
use ASH\XMLProcessor\DB\Model\Repositories\UserRepository;
use ASH\XMLProcessor\XML\Converter\UserDetailsConverter;
use ASH\XMLProcessor\XML\Reader\Parser\ChunkParser;
use ASH\XMLProcessor\XML\Reader\Stream\Provider\File as ReaderFile;
use ASH\XMLProcessor\XML\Reader\XMLReader;
use ASH\XMLProcessor\Service\XMLProcessor;
use ASH\XMLProcessor\XML\Writer\XmlWriter;
use ASH\XMLProcessor\XML\Writer\Stream\Provider\File as WriterFile;
use ASH\XMLProcessor\Util\DataGenerator;
use Exception;
use InvalidArgumentException;
use PDOException;

class ConsoleApp
{
    /**
     * Holds application config
     * @var array
     */
    private $config;

    /**
     * Holds parsed options, specified by user
     * @var array
     */
    private $parsedOptions;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'db_host' => null,
            'db_name' => null,
            'db_user' => null,
            'db_password' => null,
            'db_port' => 3306
        ], $config);

        set_error_handler(function ($errorNumber, $errorText, $errorFile, $errorLine) {
            throw new \ErrorException($errorText, 0, $errorNumber, $errorFile, $errorLine);
        });
    }

    /**
     * Runs the app
     * @return void
     */
    public function run()
    {
        if ($this->parsedOptions = getopt(implode('', ['a::', 's::', 't::', 'h']), ['filter-expression::'])) {
            $this->execute();
        } else {
            $this->println('You have to specify action, use help(-h) for more details');
        }
    }

    /**
     * Executes commands, depending on user input
     * @return void
     */
    private function execute()
    {
        if (isset($this->parsedOptions['h'])) {
            $this->printHelp();
        } else {

            try {
                $action = $this->getOption('a');
                $dbManger = null;
                $xmlProcessor = new XMLProcessor();

                if ($action !== 'generate-source-file') {
                    if (!$this->config['db_host'] || !$this->config['db_name']
                        || !$this->config['db_user']
                        || !$this->config['db_password']
                        || !$this->config['db_port']
                    ) {
                        throw new InvalidArgumentException('Please check parameters of db connection!!!');
                    }

                    $dbManger = new DatabaseManager($this->config['db_host'],
                        $this->config['db_name'],
                        $this->config['db_user'],
                        $this->config['db_password'],
                        $this->config['db_port']);

                    if ($action !== 'init') {
                        $userRepository = new UserRepository($dbManger->connect());
                        $xmlProcessor->setUserRepository($userRepository);
                    }
                }

                switch ($action) {
                    case 'init':
                        $dbManger->initDB();
                        $this->println('DB and tables were created!!');
                        break;
                    case 'import':
                        $xmlProcessor->setReader(new XMLReader(new ReaderFile(), new ChunkParser()));
                        $xmlProcessor->setConverter(new UserDetailsConverter());
                        $sourceFile = $this->getOption('s');

                        $this->println($xmlProcessor->import($sourceFile) . ' - item(s) was imported');
                        break;
                    case 'filter':
                        $targetFile = $this->getOption('t');
                        $filterExpression = $this->getOption('filter-expression');

                        $xmlProcessor->setWriter(new XmlWriter(new WriterFile()));
                        $xmlProcessor->setConverter(new UserDetailsConverter());

                        $this->println('Item(s) found - ' . $xmlProcessor->filter($filterExpression, $targetFile));
                        break;
                    case 'generate-source-file':
                        $targetFile = $this->getOption('t');
                        if (!$targetFile) {
                            $this->println('Please specify target file');
                        } else {
                            DataGenerator::generateSourceFile($targetFile);
                            $this->println('The source file was successfully generated!!');

                        }
                        break;
                    case 'clean':
                        $xmlProcessor->cleanCachedData();
                        $this->println('The stored data was removed');
                        break;
                    default:
                        $this->println('Undefined action', true);
                }
            } catch (PDOException $e) {
                $message = $e->getMessage();

                if (intval($e->getCode()) === 23000) {
                    $message = 'Please clean the previously imported data';
                }

                $this->println($message, true);
            } catch (Exception $e) {
                $this->println($e->getMessage(), true);
            }
        }
    }

    /**
     * Prints help info
     */
    private function printHelp()
    {
        $help = <<<HELP
XML processor

OPTIONS
    -a                  Action name
    -s                  Path to source file
    -t                  Path to output file
    --filter-expression Filtering expression which will be applied to imported data
    -h                  Help
COMMANDS
    init:
        Usage: -a=init
        Creates db and tables
    import:
        Usage: -a=import -s=users.xml
        Grabs data from xml and stores it to db
    filter:
        Usage: -a=filter -t=users-filtered.xml --filter-expression="age btw 20 45"
        Filters previously imported data and stores results to the target file 
    generate:
        Usage: -a=generate-source-file -t=users.xml
        Generates xml file for testing
    clean:
        Usage: -a=clean
        Truncates the users table
HELP;
        $this->println($help);
    }

    /**
     * Gets an option value by name
     * @param string $name The name of option
     * @return mixed|null Returns the option value
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