<?php

namespace Deisss\Automodel\Console\Commands;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Create all the models related to the current database structure.
 *
 * Class AutomodelDatabase
 * @package Deisss\Automodel\Console\Commands
 */
class AutomodelDatabase extends AbstractGeneratorCommand
{
    /*
     * ------------------------------------------
     *   COMMAND
     * ------------------------------------------
     */
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'automodel:database
        {--tables=* : If you want to render the model of only few tables (coma separated)}
        {--overwrite : Allow the system to overwrite a previously existing model (default: false)}
        {--erase-models : If the command should erase the app/Models folder every time before creating models}
        {--skip-config : skip the configuration step (the .schema file creation and update)}
        {--skip-models : skip the database to models creation}
        {--skip-check : avoid including models to detect failure...}
        {--force : By default, the command stop itself when running in production mode, this parameter skip this check and allow to proceed in production environment}
        {--yes : automatically says yes to every questions the command may ask you...}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a .schema file at the root of the project/or update it, then create all the models related to it.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /*
     * ------------------------------------------
     *   HELPERS
     * ------------------------------------------
     */
    /**
     * Remove a directory and all of it's content.
     *
     * @param string $dir The directory to remove.
     * @return bool True if the command succeed and everything has been cleaned/erased.
     */
    public function recursiveDeleteDirectory($dir) {
        // Not existing yet
        if (!file_exists($dir)) {
            return true;
        }
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $tmp = $dir.DIRECTORY_SEPARATOR.$file;
            (is_dir($tmp)) ? $this->recursiveDeleteDirectory($tmp) : unlink($tmp);
        }
        return rmdir($dir);
    }

    /**
     * Convert various type of rename written into a unique syntax for the automodel table behind.
     *
     * @param null|string $key The key to parse
     * @param null|string $value The value to parse
     * @return null|string The result string or null if the system wasn't able to do anything with the rename provided...
     */
    protected function extractRenameFromParameters($key, $value)
    {
        // Normal array
        if (is_int($key)) {
            if (!empty($value)) {
                $pattern = '/\s*(?P<key>[a-zA-Z0-9\_\-]+)\s*\:?\s*(?P<source_field>[a-zA-Z0-9\_\-]+)?\s*>?\s*(?P<target_table>[a-zA-Z0-9\_\-]+)?\s*\|?\s*(?P<target_field>[a-zA-Z0-9\_\-]+)?\s*/';
                preg_match($pattern, $value, $matches);

                if (array_key_exists('key', $matches) && !empty($matches['key'])) {
                    $innerKey = $matches['key'];

                    $sourceField = '';
                    $targetTable = '';
                    $targetField = '';

                    if (array_key_exists('source_field', $matches) && !empty($matches['source_field'])) {
                        $sourceField = $matches['source_field'];
                    }
                    if (array_key_exists('target_table', $matches) && !empty($matches['target_table'])) {
                        $targetTable = $matches['target_table'];
                    }
                    if (array_key_exists('target_field', $matches) && !empty($matches['target_field'])) {
                        $targetField = $matches['target_field'];
                    }

                    return $innerKey.':'.$sourceField.'>'.$targetTable.'|'.$targetField;
                }
                // Dead end
                else {
                    return null;
                }
            }

        }
        // Associative array
        else if (!empty($key)) {
            $pattern = '/\s*(?P<source_field>[a-zA-Z0-9\_\-]+)?\s*>?\s*(?P<target_table>[a-zA-Z0-9\_\-]+)?\s*\|?\s*(?P<target_field>[a-zA-Z0-9\_\-]+)?\s*/';
            preg_match($pattern, $value, $matches);

            $sourceField = '';
            $targetTable = '';
            $targetField = '';

            if (array_key_exists('source_field', $matches) && !empty($matches['source_field'])) {
                $sourceField = $matches['source_field'];
            }
            if (array_key_exists('target_table', $matches) && !empty($matches['target_table'])) {
                $targetTable = $matches['target_table'];
            }
            if (array_key_exists('target_field', $matches) && !empty($matches['target_field'])) {
                $targetField = $matches['target_field'];
            }

            // This is a specific way where we ask to not proceed this key...
            if (empty($sourceField) && empty($targetTable) && empty($targetField)) {
                return $key.':';
            }
            // something to process
            else {
                return $key.':'.$sourceField.'>'.$targetTable.'|'.$targetField;
            }
        }

        return null;
    }

    /**
     * Convert various type of scope written into a unique syntax for the automodel table behind.
     *
     * @param null|string $key The key to parse
     * @param null|string $value The value to parse
     * @return null|string The result string or null if the system wasn't able to do anything with the scope provided...
     */
    protected function extractScopeFromParameters($key, $value)
    {
        // Normal array
        if (is_int($key)) {
            if (!empty($value)) {
                $pattern = '/\s*(?P<key>[a-zA-Z0-9\_\-]+:)?\s*(?P<field>[a-zA-Z0-9\_\-]+)?\s*(?P<operator>[\<\>\=\!]+)?\s*(?P<content>[\$a-zA-Z0-9\_\-\"\']+)?\s*/';
                preg_match($pattern, $value, $matches);

                $key = '';

                if (array_key_exists('key', $matches) && !empty($matches['key'])) {
                    $key = $matches['key'];
                } else if (array_key_exists('field', $matches) && !empty($matches['field'])) {
                    $key = $matches['field'].':';
                }

                // We got something to continue
                if (!empty($key)) {
                    $field    = $matches['field'];
                    $operator = '=';
                    $content  = '$'.$field;

                    if (array_key_exists('operator', $matches) && !empty($matches['operator'])) {
                        $operator = $matches['operator'];
                    }

                    if (array_key_exists('content', $matches) && !empty($matches['content'])) {
                        $content = $matches['content'];
                    }

                    return $key.$field.$operator.$content;
                }
                // This is a dead end, we have no information to proceed.
                else {
                    return null;
                }
            }
            // This is a dead end, we have no information to proceed.
            else {
                return null;
            }
        }
        // Associative array
        else if (!empty($key)) {
            if (!empty($value)) {
                $pattern = '/\s*(?P<field>[a-zA-Z0-9\_\-]+)?\s*(?P<operator>[\<\>\=\!]+)?\s*(?P<content>[\$a-zA-Z0-9\_\-\"\']+)?\s*/';
                preg_match($pattern, $value, $matches);

                $field = $key;
                $operator = '=';
                $content  = '$'.$field;

                if (array_key_exists('field', $matches) && !empty($matches['field'])) {
                    $field = $matches['field'];
                }

                if (array_key_exists('operator', $matches) && !empty($matches['operator'])) {
                    $operator = $matches['operator'];
                }

                if (array_key_exists('content', $matches) && !empty($matches['content'])) {
                    $content = $matches['content'];
                }

                return $key.':'.$field.$operator.$content;
            }
            // The most simple case
            else {
                return $key.':'.$key.'=$'.$key;
            }
        }
        return null;
    }

    /*
     * ------------------------------------------
     *   RESULT CHECK
     * ------------------------------------------
     */
    /**
     * Trying to load all models generated during this session, to check if they have
     * a problem or not (they may be some collisions to detect for example).
     *
     * @param array $tables The subset table to refine the loading on.
     */
    protected function checkResult($tables = null)
    {
        $onlyVerboseLevel = OutputInterface::VERBOSITY_VERBOSE;
        $this->title('RESULT CHECK', $onlyVerboseLevel);
        $this->info('If an error occurs here, most of the time it\'s a collision (twice the same function name)');
        $schema   = $this->getSchemaContent();

        foreach ($schema as $item) {
            // In some situations the user may want to render just a subset...
            if (is_array($tables) && !empty($tables) && !in_array($item['table'], $tables)) {
                $this->info('Skipping model for table: "'.$item['table'].'", table is marked as "skip"');
                continue;
            }

            // Trying to load
            if (array_key_exists('folder', $item) && array_key_exists('name', $item) &&
                (!array_key_exists('pivot', $item) || $item['pivot'] !== true) &&
                (!array_key_exists('skip', $item) || $item['skip'] !== true)
            ) {
                $path = $item['folder'].DIRECTORY_SEPARATOR.$item['name'].'.php';

                try {
                    if (!@include_once($path)) {
                        throw new \Exception('The file does not exists');
                    }
                    if (!file_exists($path)) {
                        throw new \Exception('The file does not exists');
                    } else {
                        require_once($path);
                    }
                } catch(\Exception $e) {
                    $this->info('Error while loading model: "'.$path.'":');
                    $this->info("\t".$e->getMessage());
                    $this->info("\t".$e->getCode());
                }
            } else {
                $this->info('Skipping model for table: "'.$item['table'].'", table is missing folder and/or name property (or skip/pivot table)');
            }
        }
    }

    /*
     * ------------------------------------------
     *   HANDLER
     * ------------------------------------------
     */
    /**
     * Handle the configuration file creation.
     */
    protected function handleConfig()
    {
        $onlyVerboseLevel = OutputInterface::VERBOSITY_VERBOSE;
        $this->title('CREATING/UPDATING SCHEMA CONFIG', $onlyVerboseLevel);

        $database  = $this->getDatabase();
        $schema    = $this->getSchemaContent();
        // We use "/" here as it's compatible both in Windows and Linux.
        $folder    = 'app/Models';
        $namespace = 'App\Models';
        $tables    = $this->getTables($database);

        // We remove models that are not existing anymore...
        $schemaHasBeenUpdated = false;
        foreach ($schema as $key => $value) {
            $found = false;
            foreach ($tables as $table) {
                if ($table->name === $value['table']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $this->info('Removing the table "'.$value['table'].'" from the schema (not found in the database)',
                    $onlyVerboseLevel);
                $schemaHasBeenUpdated = true;
                unset($schema[$key]);
            }
        }

        // We need to remove keys
        if ($schemaHasBeenUpdated) {
            $schema = array_values($schema);
        }

        $this->info('', $onlyVerboseLevel);

        // Creating new entries if needed.
        foreach ($tables as $table) {
            $name  = $table->name;
            $found = false;

            foreach ($schema as $item) {
                if ($item['table'] == $name) {
                    $found = true;
                    break;
                }
            }

            // It's a new table, we can override
            if (!$found) {
                $this->info('Adding the table "'.$name.'" to the schema (not found in the schema config file), details:',
                    $onlyVerboseLevel);
                $this->info("\tTable:     ".$name, $onlyVerboseLevel);
                $this->info("\tName:      ".$this->tableToClass($name, true, false), $onlyVerboseLevel);
                $this->info("\tFolder:    ".$folder, $onlyVerboseLevel);
                $this->info("\tNamespace: ".$namespace, $onlyVerboseLevel);
                $this->info('', $onlyVerboseLevel);

                /*
                 * List of parameters:
                 *   - table:     the table name (if it is changed, the system will
                 *                apply the rest of this parameter on a different table).
                 *   - name:      The class name to use related to this table (the model's class).
                 *   - folder:    The folder to put the class in it, by default it should be app/models.
                 *   - namespace: The namespace to use for this model, should be related to the folder.
                 *   - renames:   List of renames to apply.
                 *   - scopes:    List of scopes to append.
                 *   - traits:    List of traits to append.
                 *   - pivot:     If it's a pivot table (not processed in this case, does not have a related model).
                 *   - skip:      Skip the model creation (used for password reset for example).
                 */

                $process    = true;
                $parameters = array(
                    'table' => $name
                );

                if ($this->isPivotTable($database, $name)) {
                    $parameters['pivot'] = true;
                    $process = false;
                }

                if ($process && in_array($name, $this->skipped)) {
                    $parameters['skip'] = true;
                    $process = false;
                }

                if ($process) {
                    $parameters['name']      = $this->tableToClass($name, true, false);
                    $parameters['folder']    = $folder;
                    $parameters['namespace'] = $namespace;
                }

                $schema[] = $parameters;
            }
        }

        // We sort it
        usort($schema, function ($a, $b) {
            return strcmp($a['table'], $b['table']);
        });

        // The schema file is empty
        if (empty($schema)) {
            $this->info('It appears the schema file you are trying to save does not contains any table, are you sure the migration is done properly?');
        }

        // We save it
        $this->info('Saving the schema config file "'.$this->getSchemaFilename().'"', $onlyVerboseLevel);
        file_put_contents($this->getSchemaFilename(), json_encode($schema, JSON_PRETTY_PRINT));
    }

    /**
     * Erase the app/Models folder before anything is done with it.
     *
     * @param boolean $autoYesAnswer If checked to true, this will not prompt any question and directly erase.
     */
    protected function handleErase($autoYesAnswer)
    {
        $onlyVerboseLevel = OutputInterface::VERBOSITY_VERBOSE;
        $this->title('ERASING PREVIOUS MODELS', $onlyVerboseLevel);

        if ($autoYesAnswer) {
            $this->info('Delete the folder app/Models and all of it\'s content?  yes');
            $this->info('Trying to delete "'.app_path('Models').'" folder');
            $this->recursiveDeleteDirectory(app_path('Models'));
        } else {
            $answer = $this->ask('Delete the folder app/Models and all of it\'s content?');
            $answer = trim(strtolower($answer));
            // English / French / German / Spanish
            if (in_array($answer, array('y', 'ye', 'yes', 'oui', 'ou', 'o', 'j', 'ja', 's', 'si'))) {
                $this->info('Trying to delete "'.app_path('Models').'" folder');
                $this->recursiveDeleteDirectory(app_path('Models'));
            } else {
                $this->info('Skipping erasing folder app/Models...');
            }
        }
    }

    /**
     * Read the configuration file and create all models related to it.
     *
     * @param boolean $overwrite If we should overwrite existing models or not.
     * @param array|null $tables The table to limit the model creation on...
     */
    protected function handleModels($overwrite, $tables = null)
    {
        $onlyVerboseLevel = OutputInterface::VERBOSITY_VERBOSE;
        $this->title('PARSING SCHEMA CONFIG', $onlyVerboseLevel);

        // The "base" template
        $template = 'automodel::model';
        $schema   = $this->getSchemaContent();

        foreach ($schema as $item) {
            // In some situations the user may want to render just a subset...
            if (is_array($tables) && !empty($tables) && !in_array($item['table'], $tables)) {
                $this->info('Skipping model for table: "'.$item['table'].'", table is marked as "skip"');
                continue;
            }

            // Pivot and specifically asked to skip are skipped
            if ((!array_key_exists('skip', $item) || $item['skip'] !== true) &&
                (!array_key_exists('pivot', $item) || $item['pivot'] !== true)) {
                $this->info('Creating model "'.$item['name'].'" (table "'.$item['table'].'")');

                $renames = array();
                if (array_key_exists('renames', $item) && is_array($item['renames'])) {
                    foreach ($item['renames'] as $key => $value) {
                        $renames[] = $key.':'.$value;
                    }
                }

                $scopes = array();
                if (array_key_exists('scopes', $item) && is_array($item['scopes'])) {
                    foreach ($item['scopes'] as $key => $value) {
                        $converted = $this->extractScopeFromParameters($key, $value);

                        if (!empty($converted)) {
                            $scopes[] = $converted;
                        }
                    }
                }

                $removes = array();
                if (array_key_exists('removes', $item) && is_array($item['removes'])) {
                    // We drop the key as we don't need it at all
                    foreach ($item['removes'] as $key => $value) {
                        $removes[] = $value;
                    }
                }

                $traits = array();
                if (array_key_exists('traits', $item) && is_array($item['traits'])) {
                    foreach ($item['traits'] as $key => $value) {
                        $traits[] = $key.':'.$value;
                    }
                }

                $modelFolder   = (array_key_exists('folder', $item)) ? $item['folder'] : 'app'.DIRECTORY_SEPARATOR.'Models';
                $modelTemplate = (array_key_exists('template', $item) && !empty($item['template'])) ? $item['template']: $template;
                $modelFillable = (array_key_exists('fillable', $item) && !empty($item['fillable'])) ? true: false;

                $this->info('Contacting automodel:table with parameters:', $onlyVerboseLevel);
                $this->info("\tTable:      ".$item['table'],     $onlyVerboseLevel);
                $this->info("\tNamespace:  ".$item['namespace'], $onlyVerboseLevel);
                $this->info("\tClass name: ".$item['name'],      $onlyVerboseLevel);
                $this->info("\tFolder:     ".$modelFolder,       $onlyVerboseLevel);
                $this->info("\tTemplate:   ".$modelTemplate,     $onlyVerboseLevel);
                if (empty($scopes)) {
                    $this->info("\tScopes:     []", $onlyVerboseLevel);
                } else {
                    $this->info("\tScopes:     ['".implode('\', \'', $scopes).'\']', $onlyVerboseLevel);
                }
                if (empty($renames)) {
                    $this->info("\tRenames:    []", $onlyVerboseLevel);
                } else {
                    $this->info("\tRenames:    ['".implode('\', \'', $renames).'\']', $onlyVerboseLevel);
                }
                if (empty($removes)) {
                    $this->info("\tRemoves:    []", $onlyVerboseLevel);
                } else {
                    $this->info("\tRemoves:    ['".implode('\', \'', $removes).'\']', $onlyVerboseLevel);
                }
                if (empty($traits)) {
                    $this->info("\tTraits:     []", $onlyVerboseLevel);
                } else {
                    $this->info("\tTraits:     ['".implode('\', \'', $traits).'\']', $onlyVerboseLevel);
                }

                $parameters = array(
                    'table'       => $item['table'],
                    '--namespace' => $item['namespace'],
                    '--name'      => $item['name'],
                    '--folder'    => $modelFolder,
                    '--template'  => $modelTemplate,
                    '--scopes'    => $scopes,
                    '--renames'   => $renames,
                    '--removes'   => $removes,
                    '--traits'    => $traits
                );

                if ($modelFillable) {
                    $this->info("\tFillable:   true", $onlyVerboseLevel);
                    $parameters['--fillable'] = true;
                }

                // Propagate the verbose
                if ($this->isVerbose()) {
                    $this->info("\tVerbose:    true", $onlyVerboseLevel);
                    $parameters['--verbose'] = true;
                }

                // Propagate the overwrite
                if ($overwrite === true) {
                    $this->info("\tOverwrite:  true", $onlyVerboseLevel);
                    $parameters['--overwrite'] = true;
                }

                // Calling the table
                $this->call('automodel:table', $parameters);
            } else if (array_key_exists('pivot', $item) && $item['pivot'] === true) {
                $this->info('Skipping model for table "'.$item['table'].'", table is a pivot table');
            } else {
                $this->info('Skipping model for table: "'.$item['table'].'", table is marked as "skip"');
            }

            $this->info('');
            $this->info('');
        }
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Testing the environment configuration before anything
        if (\App::environment('production') && $this->option('force') === false) {
            $this->error('You are running this command in a production environment, cannot proceed (use --force to skip this security check)');
            die;
        }

        /*
         * --------------------------
         *   PARAMETERS
         * --------------------------
         */
        $overwrite = $this->option('overwrite');
        $tables    = $this->option('tables');

        if (empty($overwrite)) {
            $overwrite = false;
        }

        /*
         * --------------------------
         *   HANDLERS
         * --------------------------
         */
        // Create the configuration file and/or update it
        if (empty($this->option('skip-config'))) {
            $this->handleConfig();
        }

        // Erasing
        if (!empty($this->option('erase-models'))) {
            $this->handleErase($this->option('yes'));
        }

        // Create the models
        if (empty($this->option('skip-models'))) {
            $this->handleModels($overwrite, $tables);
        }

        /*
         * --------------------------
         *   COUNTER CHECK
         * --------------------------
         */
        if (empty($this->option('skip-check'))) {
            $this->checkResult($tables);
        }
    }
}