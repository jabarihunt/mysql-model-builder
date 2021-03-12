<?php namespace jabarihunt;

    /********************************************************************************
     * GET AUTO LOADER | GET REQUIRED LIBRARIES | SET PROJECT DIRECTORY
     ********************************************************************************/

        require(__DIR__ . '/../../../autoload.php');

        use Dotenv\Dotenv;
        use jabarihunt\MySQL as DB;

        $projectPath = getcwd();

    /********************************************************************************
     * SET NAMESPACE VARIABLE
     ********************************************************************************/

        if (!empty($argv[1])) {
            $namespace = trim($argv[1]);
        }

        $namespace = !empty($namespace) ? $namespace : 'jabarihunt';

    /********************************************************************************
     * SET MODELS DIRECTORY PATH AND DIRECTORY
     ********************************************************************************/

        $modelsDirectoryPath = $projectPath;

        if (!empty($argv[2]) && !in_array($argv[2], ['.', '/'])) {

            $arg2 = trim($argv[2]);
            $arg2 = trim($arg2, '/');

            $modelsDirectoryPath = (!empty($arg2) && $arg2 !== 'models') ? "{$modelsDirectoryPath}/{$arg2}" : $modelsDirectoryPath;

        }

        $modelsDirectoryPath .= '/models';

    /********************************************************************************
     * GET .env FILE -> INSTANTIATE DOTENV
     ********************************************************************************/

        $envFound    = FALSE;
        $envFilePath = $projectPath;

        for ($i = 0; $i < 5; $i++) {

            if (file_exists($envFilePath . '/.env')) {
                $envFound = TRUE;
                break;
            } else {
                $envFilePath .= '/..';
            }

        }

        if ($envFound) {
            $dotenv = Dotenv::createImmutable($envFilePath);
            $dotenv->load();
        } else {
            echo "\r\nMODEL BUILDER: .env file not found!\r\n\r\n";
            die();
        }

    /********************************************************************************
     * PHP CLI MODEL BUILDER
     * @author Jabari J. Hunt <jabari@jabari.net>
     ********************************************************************************/

        final class ModelBuilder {

            /********************************************************************************
             * CLASS CONSTANTS
             * @var array SEARCH Array of place holders in BaseModel.php
             ********************************************************************************/

                const SEARCH = [
                    '[NAMESPACE]',
                    '[MODEL_NAME]',
                    '[MODEL_NAME_FIRST_LETTER_LOWERCASE]',
                    '[MODEL_NAME_UPPERCASE]',
                    '[CLASS_VARIABLES]',
                    '[CLASS_CONSTANT_DATA_TYPES]',
                    '[CLASS_CONSTANT_REQUIRED_FIELDS]',
                    '[TABLE_NAME]',
                    '[PRIMARY_KEY]',
                    '[GETTERS]',
                    '[TABLE_NAME_FORMATTED]',
                    '[ALL_COLUMN_NAMES]',
                    '[CREATE_METHOD_VALIDATION_CRITERIA]',
                    '[CREATE_METHOD_COLUMN_NAMES]',
                    '[CREATE_QUERY_COLUMN_PLACEHOLDERS]',
                    '[CREATE_METHOD_BIND_TYPES]',
                    '[CREATE_METHOD_BIND_DATA_STRING]'
                ];

            /********************************************************************************
             * CLASS VARIABLES
             * @var string $baseModelContent Holds base model content
             * @var string|false $model
             * @var array $replace Array of values to use in BaseModel.php (for each model)
             ********************************************************************************/

                private string $baseModelContent;
                private string|false $modelContent;

                private array $replace = [
                    'namespace'                      => '',
                    'modelName'                      => '',
                    'modelNameFirstLetterLowercase'  => '',
                    'modelNameUppercase'             => '',
                    'classVariables'                 => '',
                    'classConstantDataTypes'         => '',
                    'classConstantRequiredFields'    => '',
                    'tableName'                      => '',
                    'primaryKey'                     => '',
                    'getters'                        => '',
                    'tableNameFormatted'             => '',
                    'allColumnNames'                 => '',
                    'createMethodValidationCriteria' => '',
                    'createQueryColumnNames'         => '',
                    'createQueryColumnPlaceholders'  => '',
                    'createMethodBindTypes'          => '',
                    'createMethodBindDataString'     => ''
                ];

            /********************************************************************************
             * CLASS CONSTRUCTOR AND DESTRUCTOR
             * @param string $modelsDirectoryPath
             * @param string $namespace
             ********************************************************************************/

                public function __construct(string $modelsDirectoryPath, string $namespace) {

                    // SET NAMESPACE IN REPLACE ARRAY

                        $this->replace['namespace'] = $namespace;

                    // CREATE MODEL AND BASE MODEL DIRECTORIES

                        if (!is_dir($modelsDirectoryPath)) {
                            mkdir($modelsDirectoryPath, 0755);
                        }

                        if (!is_dir($modelsDirectoryPath . '/base')) {
                            mkdir($modelsDirectoryPath . '/base', 0755);
                        }

                    // GET BASE MODEL | GET TABLE DATA

                        $this->prompt("\nStarting Base Model Builder...\n", FALSE);

                        $this->baseModelContent = file_get_contents(__DIR__ . '/templates/BaseModel.php.template');

                        if (!empty($this->baseModelContent)) {
                            $this->prompt('Retrieved base model template');
                        }

                        $this->modelContent = file_get_contents(__DIR__ . '/templates/WorkingModel.php.template');

                        if (!empty($this->modelContent)) {
                            $this->prompt('Retrieved model template');
                        }

                        $tableNames = $this->getTables();

                        if (is_array($tableNames) && count($tableNames) > 0) {
                            $this->prompt('Preparing to build ' . count($tableNames) . ' table(s)');
                        }

                    // COPY MODEL FILE

                        $content = file_get_contents(__DIR__ . '/templates/Model.php.template');
                        $content = str_replace(ModelBuilder::SEARCH, $this->replace, $content);
                        file_put_contents($modelsDirectoryPath . '/base/Model.php', $content);

                    // BUILD BASE MODEL FOR EACH TABLE AND SAVE

                        foreach ($tableNames as $tableName) {

                            // CREATE MODEL DATA | PROMPT USER | RESET REPLACE ARRAY

                                $tableBuilt = $this->buildBaseModel($tableName, $modelsDirectoryPath);
                                $tableBuilt ? $this->prompt("COMPLETED: {$tableName}") : $this->prompt("ERROR: {$tableName}");
                                $this->resetReplaceArray($namespace);

                        }

                }

                public function __destruct() {
                    $this->prompt("\n", FALSE);
                }

            /********************************************************************************
             * GET TABLES METHOD
             * @return array
             ********************************************************************************/

                private function getTables(): array {

                    // SET INITIAL VARIABLES | GET TABLE NAMES | RETURN TABLES

                        $tableNames = [];
                        $results    = DB::query('SHOW TABLES');

                        foreach ($results as $tableArray) {
                            $tableNames[] = $tableArray[array_key_first($tableArray)];
                        }

                        return $tableNames;

                }

            /********************************************************************************
             * BUILD BASE MODEL METHOD
             * @param string $tableName
             * @param string $modelsDirectoryPath
             * @return boolean
             ******************************************************************************
             */

                private function buildBaseModel(string $tableName, string $modelsDirectoryPath): bool {

                    // GET TABLE COLUMN INFO | SET INITIAL RETURN VALUE

                        $columns    = DB::query("DESCRIBE {$tableName}");
                        $modelBuilt = FALSE;

                    // SET INITIAL REPLACE VARIABLES

                        $this->replace['modelName']                     = $this->snakeToCamel($tableName, TRUE);
                        $this->replace['modelName']                     = $this->pluralToSingular($this->replace['modelName']);
                        $this->replace['modelNameFirstLetterLowercase'] = lcfirst($this->replace['modelName']);
                        $this->replace['modelNameUppercase']            = strtoupper($this->replace['modelName']);
                        $this->replace['tableName']                     = $tableName;

                    // LOOP THROUGH COLUMNS AND SET REMAINING VARIABLES

                    /* EXAMPLE COLUMN DATA
                    *
                    *   array(6) {
                    *    ["Field"]=>
                    *    string(2) "id"
                    *    ["Type"]=>
                    *    string(12) "varchar(100)"
                    *    ["Null"]=>
                    *    string(2) "NO"
                    *    ["Key"]=>
                    *    string(3) "PRI"
                    *    ["Default"]=>
                    *    string(0) ""
                    *    ["Extra"]=>
                    *    string(0) ""
                    */
                        foreach ($columns as $column) {

                            // DO ANY VALUE PREP THAT IS REQUIRED

                                if (str_contains($column['Type'], '(')) {
                                    $column['Type'] = stristr($column['Type'], '(', TRUE);
                                }

                            // SET REMAINING REPLACE VARIABLES

                                $this->replace['classVariables']                .= "                protected \${$column['Field']};\n";
                                $this->replace['classConstantDataTypes']        .= "                    '{$column['Field']}' => '{$column['Type']}',\n";
                                $this->replace['getters']                       .= '                final public function get' . $this->snakeToCamel($column['Field'], TRUE) . "() {return \$this->{$column['Field']};}\n";
                                $this->replace['allColumnNames']                .= "`{$column['Field']}`, ";

                                if (strtolower($column['Key']) != 'pri') {

                                    $this->replace['createQueryColumnNames']         .= "`{$column['Field']}`, ";
                                    $this->replace['createQueryColumnPlaceholders'] .= '?, ';
                                    $this->replace['createMethodBindDataString']    .= "\$data['{$column['Field']}'], ";

                                    if (strtolower($column['Null']) == 'no') {
                                        $this->replace['createMethodValidationCriteria'] .= "                            !empty(\$data['{$column['Field']}']) &&\n";
                                    }

                                    if (in_array($column['Type'], DB::DATA_TYPE_INTEGER)) {
                                        $this->replace['createMethodBindTypes'] .= 'i';
                                    } else {
                                        $this->replace['createMethodBindTypes'] .= 's';
                                    }

                                } else {
                                    $this->replace['primaryKey'] = $column['Field'];
                                }

                                if (strtolower($column['Null']) == 'no') {
                                    $this->replace['classConstantRequiredFields'] .= "'{$column['Field']}', ";
                                }

                        }

                        if ($this->replace['createMethodValidationCriteria'] == '') {
                            $this->replace['createMethodValidationCriteria'] = '                            TRUE';
                        }

                    // REMOVE UNNEEDED CHARACTERS FROM END OF VARIABLES

                        $this->replace['classVariables']                 = rtrim($this->replace['classVariables'], "\n");
                        $this->replace['classConstantDataTypes']         = rtrim($this->replace['classConstantDataTypes'], ",\n");
                        $this->replace['classConstantRequiredFields']    = rtrim($this->replace['classConstantRequiredFields'], ', ');
                        $this->replace['getters']                        = rtrim($this->replace['getters'], "\n");
                        $this->replace['allColumnNames']                 = rtrim($this->replace['allColumnNames'], ', ');
                        $this->replace['createMethodValidationCriteria'] = rtrim($this->replace['createMethodValidationCriteria'], " &&\n");
                        $this->replace['createQueryColumnNames']         = rtrim($this->replace['createQueryColumnNames'], ', ');
                        $this->replace['createQueryColumnPlaceholders']  = rtrim($this->replace['createQueryColumnPlaceholders'], ', ');
                        //$this->replace['createMethodBindTypes']          = rtrim($this->replace['createMethodBindTypes'], ', ');
                        $this->replace['createMethodBindDataString']     = rtrim($this->replace['createMethodBindDataString'], ', ');

                    // SAVE MODEL FILES

                        $baseModelContent = str_replace(ModelBuilder::SEARCH, $this->replace, $this->baseModelContent);
                        $baseModelFile    = "{$modelsDirectoryPath}/base/{$this->replace['modelName']}Model.php";
                        $fileSaved        = file_put_contents($baseModelFile, $baseModelContent);

                        if ($fileSaved !== FALSE) {

                            $modelFile = "{$modelsDirectoryPath}/{$this->replace['modelName']}.php";

                            if (!file_exists($modelFile)) {

                                $modelContent = str_replace(ModelBuilder::SEARCH, $this->replace, $this->modelContent);
                                $fileSaved    = file_put_contents($modelFile, $modelContent);

                            }

                        }

                    // VERIFY FILE(S) WERE SAVED AND RETURN RESULT

                        if ($fileSaved !== FALSE) {
                            $modelBuilt = TRUE;
                        }

                        return $modelBuilt;

                }

            /********************************************************************************
             * PLURAL TO SINGULAR
             * @param string $word Word to be made singular
             * @return string
             ********************************************************************************/

                public static function pluralToSingular(string $word): string {

                    if (strlen($word) > 0) {

                        // SET INITIAL VARIABLES

                        $firstLetter      = $word[0];
                        $specialCaseWords = [];

                        $wordEndings = [
                            'ies' => 'y',
                            'oes' => 'oe',
                            'ves' => 'f',
                            'xes' => 'x',
                            'os'  => 'o',
                            's'   => ''
                        ];

                        // HANDLE WORD TYPE

                        if (array_key_exists(strtolower($word), $specialCaseWords)) {
                            $word = $specialCaseWords[$word];
                        } else {

                            // LOOP THROUGH WORD ENDINGS -> BUILD WORD ON MATCH

                            foreach($wordEndings as $ending => $replacement) {

                                if (substr($word, (strlen($ending) * -1)) == $ending) {

                                    $word  = substr($word, 0, strlen($word) - strlen($ending));
                                    $word .= $replacement;
                                    break;

                                }

                            }

                        }

                        // REPLACE THE FIRST LETTER WITH WHATEVER THE ORIGINAL WAS

                        $word[0] = $firstLetter;

                    }

                    return $word;

                }

            /********************************************************************************
             * SNAKE CASE TO CAMEL CASE METHOD
             * @param string $value String value to be converted to camel case.
             * @param bool $firstLetterUpper Determines if the first letter should be upper case
             * @return string
             ********************************************************************************/

                public static function snakeToCamel(string $value, bool $firstLetterUpper = FALSE): string {

                    if (strlen($value) > 0) {

                        $value = str_replace('_', ' ', strtolower($value));
                        $value = str_replace(' ', '', ucwords($value));

                        if (!$firstLetterUpper) {$value = lcfirst($value);}

                    }

                    return $value;

                }

            /********************************************************************************
             * RESET REPLACE ARRAY METHOD
             * @return void
             ********************************************************************************/

                private function resetReplaceArray($namespace): void {

                    foreach ($this->replace as $key => $value) {
                        if ($key !== 'namespace') {
                            $this->replace[$key] = '';
                        }
                    }

                }

            /********************************************************************************
             * PROMPT METHOD
             * @param string $message
             * @param boolean $displayDash
             ********************************************************************************/

                private function prompt(string $message, bool $displayDash = TRUE): void {

                    if ($displayDash) {
                        $message = '- ' . $message;
                    }

                    echo "{$message}\n";

                }

        }

        new ModelBuilder($modelsDirectoryPath, $namespace);

?>