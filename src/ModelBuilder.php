<?php namespace jabarihunt\Models;

    /********************************************************************************
     * GET .env FILE
     ********************************************************************************/

        $envFound    = FALSE;
        $envFilePath = "/../../../../";

        for ($i = 0; $i < 5; $i++) {

            if (file_exists(__DIR__ . $envFilePath . '.env')) {

                $envFilePath = (__DIR__ . $envFilePath);
                //$envFilePath = rtrim($envFilePath, '/.env');
                $envFound    = TRUE;
                break;

            } else {
                $envFilePath = '/..' . $envFilePath;
            }

        }

        if (!$envFound) {

            echo "\r\nMODEL BUILDER: .env file not found!\r\n\r\n";
            die();

        }

    /********************************************************************************
     * GET MODELS DIRECTORY PATH FROM PASSED ARGUMENT
     ********************************************************************************/

        if (!empty($argv[1])) {

            $modelsDirectoryPath = trim($argv[1]);
            $modelsDirectoryPath = rtrim($argv[1], '/');

            if (!is_dir($modelsDirectoryPath)) {

                echo "\r\nMODEL BUILDER: Invalid directory provided!\r\n\r\n";
                echo "MODEL BUILDER: php ModelBuilder.php '/path/to/working/directory'\r\n\r\n";
                die();

            } else if (substr($modelsDirectoryPath, -1, 6) !== 'models') {
                $modelsDirectoryPath = "{$modelsDirectoryPath}/models";
            }

            if (!is_dir($modelsDirectoryPath)) {
                mkdir($modelsDirectoryPath, 0755);
            }

        } else {

            echo "\r\nMODEL BUILDER: Missing arguments!\r\n";
            echo "MODEL BUILDER: php ModelBuilder.php '/path/to/working/directory'\r\n\r\n";
            die();

        }

    /********************************************************************************
     * AUTO LOAD | INSTANTIATE REQUIRED LIBRARIES -> DOTENV | DB
     ********************************************************************************/

        require(__DIR__ . '/../../../autoload.php');

        use Dotenv\Dotenv;
        use jabarihunt\MySQL as DB;

        $dotenv = Dotenv::createImmutable($envFilePath);
        $dotenv->load();

    /********************************************************************************
     * PHP CLI MODEL BUILDER
     * @author Jabari J. Hunt <jabari@jabari.net>
     ********************************************************************************/

        final class ModelBuilder {

            /********************************************************************************
             * CLASS CONSTANTS
             * @var array SEARCH Array of place holders in BaseModel.php.template
             ********************************************************************************/

                const SEARCH = [
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
             * @var string $baseModel Holds base model text
             * @var array $replace Array of values to use in BaseModel.php.template (for each model)
             ********************************************************************************/

                private $baseModel;
                private $model;

                private $replace = [
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
             ********************************************************************************/

                public function __construct($modelsDirectoryPath) {

                    // GET BASE MODEL | GET TABLE DATA

                        $this->prompt("\nStarting Base Model Builder...\n", FALSE);

                        $this->baseModel = file_get_contents(__DIR__ . '/BaseModel.php.template');

                        if (!empty($this->baseModel)) {
                            $this->prompt('Retrieved base model template');
                        }

                        $this->model = file_get_contents(__DIR__ . '/WorkingModel.php.template');

                        if (!empty($this->model)) {
                            $this->prompt('Retrieved model template');
                        }

                        $tableNames = $this->getTables();

                        if (is_array($tableNames) && count($tableNames) > 0) {
                            $this->prompt('Preparing to build ' . count($tableNames) . ' table(s)');
                        }

                    // BUILD BASE MODEL FOR EACH TABLE AND SAVE

                        foreach ($tableNames as $tableName) {

                            // CREATE MODEL DATA | PROMPT USER | RESET REPLACE ARRAY

                                $tableBuilt = $this->buildBaseModel($tableName, $modelsDirectoryPath);
                                $tableBuilt ? $this->prompt("COMPLETED: {$tableName}") : $this->prompt("ERROR: {$tableName}");
                                $this->resetReplaceArray();

                        }

                    // COPY MODEL FILE IF IT DOESN'T EXIST

                        if (!file_exists(__DIR__ . 'models/Model.php.template')) {
                            copy((__DIR__ . '/Model.php.template'), (__DIR__ . '/models/Model.php'));
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

                        while($row = $results->fetch_row()) {

                            if ($row[0] != 'sessions') {
                                $tableNames[] = $row[0];
                            }

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

                        $results    = DB::query("DESCRIBE {$tableName}");
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
                        while($column = $results->fetch_assoc()) {

                            // DO ANY VALUE PREP THAT IS REQUIRED

                                if (strpos($column['Type'], '(') !== FALSE) {
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

                        $baseModel     = str_replace(ModelBuilder::SEARCH, $this->replace, $this->baseModel);
                        $baseModelFile = __DIR__ . "/models/{$this->replace['modelName']}Model.php.template";
                        $fileSaved     = file_put_contents($baseModelFile, $baseModel);

                        if ($fileSaved !== FALSE) {

                            $modelFile = "{$modelsDirectoryPath}/{$this->replace['modelName']}.php";

                            if (!file_exists($modelFile)) {

                                $model     = str_replace(ModelBuilder::SEARCH, $this->replace, $this->model);
                                $fileSaved = file_put_contents($modelFile, $model);

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

                private function resetReplaceArray(): void {

                    foreach ($this->replace as $key => $value) {
                        $this->replace[$key] = '';
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

        new ModelBuilder($modelsDirectoryPath);

?>