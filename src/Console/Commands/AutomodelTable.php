<?php

namespace Deisss\Automodel\Console\Commands;

use DB;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Create a model related to a given table.
 *
 * Class AutomodelTable
 * @package Deisss\Automodel\Console\Commands
 */
class AutomodelTable extends AbstractGeneratorCommand
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
    protected $signature = 'automodel:table
        {table : The table name to create the related model}
        {--name= : The class name (if you want to override the table name)}
        {--namespace= : The namespace to use (default: App\\Models)}
        {--folder= : The folder to store the class into (default: app/Models)}
        {--template= : The template path to use (default: resources/views/models/default.blade.php)}
        {--scopes=* : The scopes to create (multiple values allowed)}
        {--renames=* : The renames to apply on relationships (multiple values allowed)}
        {--traits=* : The traits to use (multiple values allowed)}
        {--removes=* : Disallow a relationship to be printed - it will be skipped (multiple values allowed)}
        {--fillable : If set, the system will fill the fillable array with all the fields found except: "id" and "deleted_at"}
        {--force : By default, the command stop itself when running in production mode, this parameter skip this check and allow to proceed in production environment}
        {--overwrite : Allow the system to overwrite a previously existing model (default: false)}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dump a table an create a related model for it.';

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
     * Support the rename feature. Will override a name that is supposed to be rename on the fly.
     *
     * @param string $name The name to check.
     * @param object $relationship The full relationship object.
     * @param array $renames The list of renames we have to apply.
     * @return string The renamed name.
     */
    protected function applyRename($name, &$relationship, &$renames)
    {
        if (empty($renames) || !is_array($renames)) {
            return $name;
        }

        // This pattern tries to find "rename:source_field>target_table|target_field" pattern (including whitespace debunk).
        $pattern = '/\s*(?P<rename>[a-zA-Z0-9\_\-]+)\s*:\s*(?P<source_field>[a-zA-Z0-9\_\-]+)?\s*>?\s*(?P<target_table>[a-zA-Z0-9\_\-]+)?\s*\|?\s*(?P<target_field>[a-zA-Z0-9\_\-]+)?\s*/';

        // We are still here it means there is something to rename
        foreach ($renames as $command) {
            if (!empty($command)) {
                $rename      = '';
                $sourceField = '';
                $targetTable = '';
                $targetField = '';
                $valid = true;

                preg_match($pattern, $command, $matches);

                if (array_key_exists('rename', $matches)) {
                    $rename = $matches['rename'];
                }
                if (array_key_exists('source_field', $matches)) {
                    $sourceField = $matches['source_field'];
                }
                if (array_key_exists('target_table', $matches)) {
                    $targetTable = $matches['target_table'];
                }
                if (array_key_exists('target_field', $matches)) {
                    $targetField = $matches['target_field'];
                }

                // Problem somewhere... it can be purely empty...
                if (empty($rename) || (empty($sourceField) && empty($targetTable) && empty($targetField))) {
                    continue;
                }

                // The table can be checked no matter what the relationship is.
                if (!empty($targetTable) && $targetTable !== $relationship->table) {
                    $valid = false;
                }

                // Only belongsToMany is a tricky case
                if ($relationship->type === 'belongsToMany') {
                    // There is no source key...
                    if (!empty($targetField)) {
                        if ($targetField !== $relationship->foreign_key && $targetField !== $relationship->other_key) {
                            $valid = false;
                        }
                    }
                }
                // belongsTo, hasMany and hasOne are all the same
                else {
                    if (!empty($sourceField) && $sourceField !== $relationship->other_key) {
                        $valid = false;
                    }
                    if (!empty($targetField) && $targetField !== $relationship->foreign_key) {
                        $valid = false;
                    }
                }

                // Only if it pass the whole check, we return the new name
                if ($valid) {
                    return $rename;
                }
            }
        }

        // nothing to do, it was the right name since the beginning...
        return $name;
    }

    /**
     * Support the remove feature. Will "cancel" an potential relationship to create.
     *
     * @param object $relationship The full relationship object.
     * @param array $removes The list of removes we have to apply.
     * @return boolean True if we should remove, false otherwise
     */
    protected function applyRemove(&$relationship, &$removes)
    {
        if (empty($removes) || !is_array($removes)) {
            return false;
        }

        // This pattern tries to find "source_field>target_table|target_field" pattern (including whitespace debunk).
        $pattern = '/\s*(?P<source_field>[a-zA-Z0-9\_\-]+)?\s*>?\s*(?P<target_table>[a-zA-Z0-9\_\-]+)?\s*\|?\s*(?P<target_field>[a-zA-Z0-9\_\-]+)?\s*/';

        // We are still here it means there is something to rename
        foreach ($removes as $command) {
            if (!empty($command)) {
                $sourceField = '';
                $targetTable = '';
                $targetField = '';
                $valid = true;

                preg_match($pattern, $command, $matches);

                if (array_key_exists('source_field', $matches)) {
                    $sourceField = $matches['source_field'];
                }
                if (array_key_exists('target_table', $matches)) {
                    $targetTable = $matches['target_table'];
                }
                if (array_key_exists('target_field', $matches)) {
                    $targetField = $matches['target_field'];
                }

                // Problem somewhere... it can be purely empty...
                if (empty($sourceField) && empty($targetTable) && empty($targetField)) {
                    continue;
                }

                // Testing the table
                if (!empty($targetTable) && $targetTable !== $relationship->table) {
                    $valid = false;
                }

                // Only belongsToMany is a tricky case
                if ($relationship->type === 'belongsToMany') {
                    // There is no source key...
                    if (!empty($targetField)) {
                        if ($targetField !== $relationship->foreign_key && $targetField !== $relationship->other_key) {
                            $valid = false;
                        }
                    }
                }
                // belongsTo, hasMany and hasOne are all the same
                else {
                    if (!empty($sourceField) && $sourceField !== $relationship->other_key) {
                        $valid = false;
                    }
                    if (!empty($targetField) && $targetField !== $relationship->foreign_key) {
                        $valid = false;
                    }
                }

                if ($valid) {
                    return true;
                }
            }
        }

        // nothing to do, nothing to delete
        return false;
    }

    /**
     * Support the scope feature. Will rework the given scope string into a usable content.
     *
     * @param string $content The content to rename.
     * @return object The result found.
     */
    protected function applyScope($content)
    {
        if (empty($content)) {
            return null;
        }

        // If we are in a single string mode, it's a more easy case handled
        // almost like this...
        if (preg_match('/^[a-zA-Z0-9\-\_]{1,}$/', $content)) {
            $result = new \stdClass();
            $result->name = $content;
            $result->symbol = '=';
            return $result;
        }

        $pattern = '/\s*(?P<key>[a-zA-Z0-9\_\-]+:)?\s*(?P<field>[a-zA-Z0-9\_\-]+)?\s*(?P<operator>[\<\>\=\!]+)?\s*(?P<content>[\$a-zA-Z0-9\_\-\"\']+)?\s*/';
        preg_match($pattern, $content, $matches);

        $key = '';

        if (array_key_exists('key', $matches) && !empty($matches['key'])) {
            $key = $matches['key'];
            // Removing the ':'
            $key = substr($key, 0, -1);
        } else if (array_key_exists('field', $matches) && !empty($matches['field'])) {
            $key = $matches['field'];
        }

        // We got something to continue
        if (!empty($key)) {
            $field    = $key;
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


            $result = new \stdClass();
            $result->name = $key;
            $result->field = $field;
            $result->value = null;
            $result->variable = null;

            // There is no variable in the content, we use it like this
            if (strpos($content, '$') === false && (is_numeric($content) || strpos($content, '"') !== false || strpos($content, "'") !== false)) {
                $result->value = $content;
            } else if (strpos($content, '$') === false && !is_numeric($content) && strpos($content, '"') === false && strpos($content, "'") === false) {
                // we "stringify" it kind of
                $result->value = "'".$content."'";
            } else if (strpos($content, '$') !== false) {
                $result->variable = $content;
            }

            $result->symbol = $operator;
            return $result;
        }
        // This is a dead end, we have no information to proceed.
        else {
            return null;
        }
    }

    /**
     * Support the trait feature, allowing to include any trait to the current model.
     *
     * @param string $content The content to parse.
     * @return object The result found.
     */
    protected function applyTrait($content)
    {
        // Empty or nothing to split
        if (empty($content) || strpos($content, ':') === false) {
            return null;
        }

        $split = explode(':', $content, 2);
        $result = new \stdClass();
        $result->name = trim($split[0]);
        $result->use = trim($split[1]);
        return $result;
    }

    /**
     * Apply few basic tricks to make the rendering little bit prettier...
     *
     * @param string $content The content to parse and render in a better way.
     * @return string The pretty version of the input.
     */
    protected function prettyOutput($content)
    {
        // List of pattern and replace to apply
        $patterns = array(
            // 2 space or more, replace by a single one
            array(
                '/( {2,})/',
                ' '
            ),
            // Space at the end of an array, replace by no space
            array(
                '/( \])/',
                ']'
            ),
            // Space at the end of a function parameter, replace by no space
            array(
                '/( \))/',
                ')'
            ),
            // Space at the end of line are replaced
            array(
                '/( \r\n)/',
                "\r\n"
            ),
            // 3 PHP_EOL or more, replace by a single one
            array(
                '/(\r\n){3,}/',
                "\r\n\r\n"
            ),
        );

        foreach ($patterns as $pattern) {
            do {
                $content = preg_replace($pattern[0], $pattern[1], $content, 1, $count);
            } while ($count);
        }

        return $content;
    }

    /*
     * ------------------------------------------
     *   HANDLER
     * ------------------------------------------
     */
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
        $onlyVerboseLevel          = OutputInterface::VERBOSITY_VERBOSE;
        $table                     = $this->argument('table');
        $name                      = $this->option('name')      ?: $this->tableToClass($table, true, true);
        $namespace                 = $this->option('namespace') ?: $this->tableToNamespace($table);
        $folder                    = $this->option('folder')    ?: $this->tableToFolder($table);
        $allowOverrideExistingFile = $this->option('overwrite') ?: false;
        $template                  = $this->option('template')  ?: $this->tableToTemplate($table);
        $scopes                    = $this->option('scopes')    ?: array();
        $renames                   = $this->option('renames')   ?: array();
        $traits                    = $this->option('traits')    ?: array();
        $fill                      = $this->option('fillable')  ?: array();
        $removes                   = $this->option('removes')   ?: array();

        // The database to use
        $database                  = $this->getDatabase();
        $filename                  = $folder.DIRECTORY_SEPARATOR.$name.".php";

        /*
         * --------------------------
         *   AVAILABILITY
         * --------------------------
         */
        // Destination folder exists
        if (!file_exists($folder) || !is_dir($folder)) {
            $this->info('The folder "'.$folder.'" does not exists, creating it with rights 0755', $onlyVerboseLevel);
            mkdir($folder, 0755, true);
        }

        // If the table is not existing, we skip it
        if (!$this->tableExists($database, $table)) {
            $this->error("The table".$table." in database ".$database." does not exists / you don't have the rights to access it");
            $this->error("Exiting");
            return;
        } else {
            $this->info("The table ".$table." in database ".$database." has been found", $onlyVerboseLevel);
        }

        // Checking the file if it exists or not, and cancel if there is no rights to overwrite.
        if (file_exists($filename) && !$allowOverrideExistingFile) {
            $this->error('The model is already existing, cannot proceed (use --overwrite to skip this check)');
            $this->error('Exiting');
            return;
        }

        $this->info('All the basics check have been done', $onlyVerboseLevel);

        /*
         * --------------------------
         *   STRUCTURE RESEARCH
         * --------------------------
         */
        $keys        = $this->getTableKeys($database, $table);
        $foreignKeys = array();
        $columns     = $this->getTableColumns($database, $table);
        $description = $this->getTableDescription($database, $table);
        $columnNames = collect($columns)->pluck("name")->toArray();
        $attributes  = $this->convertSQLVariableTypesToPHPVariableTypes($columns);
        $timestamps  = array();
        $fillables   = array();
        $extends     = 'Model';

        // Some triggers that can be useful...
        $hasCreatedAt = in_array('created_at', $columnNames)? true: false;
        $hasUpdatedAt = in_array('updated_at', $columnNames)? true: false;
        $hasDeletedAt = in_array('deleted_at', $columnNames)? true: false;

        // Filling timestamps field (checking if we need to include carbon or not basically)
        foreach ($attributes as $attribute) {
            if ($attribute->type === '\Carbon\Carbon') {
                $timestamps[] = $attribute->name;
            }
        }

        // Fillable
        if ($fill) {
            foreach ($columnNames as $column) {
                if (!in_array($column, $this->nofill)) {
                    $fillables[] = $column;
                }
            }
        }

        // Searching for foreign keys
        foreach ($keys as $key) {
            // We don't use the primary key for this, skipping
            if ($key->type === 'primary') {
                continue;
            }
            // External foreign key is a foreign key that is not in this table (for example a pivot table will have this)
            else if ($key->type === 'external_foreign_key') {
                // Checking if it's a pivot table we are referencing or not
                if ($this->isPivotTable($database, $key->table_name)) {
                    $pivotTable = $this->getTargetPivotTable($database, $key->table_name, $key->column_name);

                    if (empty($pivotTable)) {
                        $this->info('Could not find the related table behind the pivot table "'.$key->table_name.'"/"'.
                            $key->column_name.'", skipping this relationship');
                        continue;
                    }

                    // Function content
                    $belongsToMany              = new \stdClass();
                    $belongsToMany->name        = $this->classToFunction($this->tableToClass($pivotTable->table_name, true, true), false);
                    $belongsToMany->type        = 'belongsToMany';
                    $belongsToMany->model       = $this->tableToModel($pivotTable->table_name);
                    $belongsToMany->table       = $key->table_name;
                    $belongsToMany->foreign_key = $key->column_name;
                    $belongsToMany->other_key   = $pivotTable->referenced_column_name;

                    // Getting the pivot content to add to the query.
                    $pivotTableColumns = $this->getTableColumns($database, $key->table_name);
                    $pivotHasCreatedAt = false;
                    $pivotHasUpdatedAt = false;
                    $pivotHasDeletedAt = false;
                    foreach ($pivotTableColumns as $key => $column) {
                        // Removing "bad" pivot content
                        if ($column->name === $belongsToMany->foreign_key || $column->name === $pivotTable->referenced_column_name) {
                            unset($pivotTableColumns[$key]);
                            continue;
                        }
                        // "Replace" by custom content
                        if ($column->name === 'created_at') {
                            $pivotHasCreatedAt = true;
                            unset($pivotTableColumns[$key]);
                            continue;
                        }
                        if ($column->name === 'updated_at') {
                            $pivotHasUpdatedAt = true;
                            unset($pivotTableColumns[$key]);
                            continue;
                        }
                        if ($column->name === 'deleted_at') {
                            $pivotHasDeletedAt = true;
                            unset($pivotTableColumns[$key]);
                            continue;
                        }
                    }

                    // This part is specific to the pivot / belongs to many
                    $belongsToMany->columns = array();
                    $belongsToMany->has_deleted_at = $pivotHasDeletedAt;
                    if ($pivotHasCreatedAt && $pivotHasUpdatedAt) {
                        $belongsToMany->has_timestamps = true;
                    } else {
                        $belongsToMany->has_timestamps = false;
                        if ($pivotHasCreatedAt) {
                            $belongsToMany->columns[] = 'created_at';
                        }
                        if ($pivotHasUpdatedAt) {
                            $belongsToMany->columns[] = 'updated_at';
                        }
                    }

                    // Populating the "rest"
                    foreach ($pivotTableColumns as $column) {
                        if (!in_array($column->name, $belongsToMany->columns)) {
                            $belongsToMany->columns[] = $column->name;
                        }
                    }

                    // Attribute content
                    $attribute = new \stdClass();
                    $attribute->name = $belongsToMany->name;
                    $attribute->type = '\\'.$belongsToMany->model.'[]';

                    if (!$this->applyRemove($belongsToMany, $removes)) {
                        $foreignKeys[] = $belongsToMany;
                        $attributes[]  = $attribute;
                    }
                } else {
                    // If it has a primary key associated, it's a one to one relationship
                    if ($this->isTableColumnIsUnique($database, $key->table_name, $key->column_name)) {

                        // Function content
                        $hasOne              = new \stdClass();
                        $hasOne->name        = $this->classToFunction($this->tableToClass($key->table_name, true, true), true);
                        $hasOne->type        = 'hasOne';
                        $hasOne->model       = $this->tableToModel($key->table_name);
                        $hasOne->table       = $key->table_name;
                        $hasOne->foreign_key = $key->column_name;
                        $hasOne->other_key   = $key->referenced_column_name;

                        // Attribute content
                        $attribute = new \stdClass();
                        $attribute->name = $hasOne->name;
                        $attribute->type = '\\'.$hasOne->model;

                        if (!$this->applyRemove($hasOne, $removes)) {
                            $foreignKeys[] = $hasOne;
                            $attributes[]  = $attribute;
                        }

                    // On to Many relationship
                    } else {

                        // Function content
                        $hasMany              = new \stdClass();
                        $hasMany->name        = $this->classToFunction($this->tableToClass($key->table_name, true, true), false);
                        $hasMany->type        = 'hasMany';
                        $hasMany->model       = $this->tableToModel($key->table_name);
                        $hasMany->table       = $key->table_name;
                        $hasMany->foreign_key = $key->column_name;
                        $hasMany->other_key   = $key->referenced_column_name;

                        // Attribute content
                        $attribute = new \stdClass();
                        $attribute->name = $hasMany->name;
                        $attribute->type = '\\'.$hasMany->model.'[]';

                        if (!$this->applyRemove($hasMany, $removes)) {
                            $foreignKeys[] = $hasMany;
                            $attributes[]  = $attribute;
                        }
                    }
                }
            }
            // Internal foreign key is basically a one to many relationship, the key exist on the table itself.
            else if ($key->type === 'internal_foreign_key') {
                $belongsTo              = new \stdClass();
                $belongsTo->name        = $this->classToFunction($this->tableToClass($key->referenced_table_name, true, true), true);
                $belongsTo->type        = 'belongsTo';
                $belongsTo->model       = $this->tableToModel($key->referenced_table_name);
                $belongsTo->table       = $key->referenced_table_name;
                $belongsTo->foreign_key = $key->referenced_column_name;
                $belongsTo->other_key   = $key->column_name;

                // Attribute content
                $attribute = new \stdClass();
                $attribute->name = $belongsTo->name;
                $attribute->type = '\\'.$belongsTo->model;

                if (!$this->applyRemove($belongsTo, $removes)) {
                    $foreignKeys[] = $belongsTo;
                    $attributes[]  = $attribute;
                }
            }

            //var_dump($key);
        }

        // Rename what has to be rename
        foreach ($foreignKeys as $foreignKey) {
            $original = $foreignKey->name;
            $foreignKey->name = $this->applyRename($original, $foreignKey, $renames);
            // Apply the rename also inside the comments section
            foreach ($attributes as $attribute) {
                // It's not a basic type, and it has the same name => we edit
                if (strpos($attribute->type, '\\') === 0 && $attribute->name === $original) {
                    $attribute->name = $foreignKey->name;
                    break;
                }
            }
        }

        // Scopes things that need to be scoped...
        $tmpScopes = array();
        foreach ($scopes as $scope) {
            $result = $this->applyScope($scope);
            if (!empty($result)) {
                $tmpScopes[] = $result;
            }
        }
        $scopes = $tmpScopes;

        // Traits
        $tmpTraits = array();
        foreach ($traits as $trait) {
            $result = $this->applyTrait($trait);
            if (!empty($trait)) {
                $tmpTraits[] = $result;
            }
        }
        $traits = $tmpTraits;

        if (empty($description)) {
            $description = 'No description found in the table comment.';
        }

        /*
         * --------------------------
         *   DEBUG/CHECKS
         * --------------------------
         */
        $this->info('Creating the model with the following information:', $onlyVerboseLevel);
        $this->info("\tTemplate:       ".$template, $onlyVerboseLevel);
        $this->info("\tTable:          ".$table, $onlyVerboseLevel);
        $this->info("\tNamespace:      ".$namespace, $onlyVerboseLevel);
        $this->info("\tClass name:     ".$name, $onlyVerboseLevel);
        $this->info("\tClass extends:  ".$extends, $onlyVerboseLevel);
        $this->info("\tDescription:    ".$description, $onlyVerboseLevel);
        $this->info("\tHas Created At: ".($hasCreatedAt ? 'true': 'false'), $onlyVerboseLevel);
        $this->info("\tHas Updated At: ".($hasUpdatedAt ? 'true': 'false'), $onlyVerboseLevel);
        $this->info("\tHas Deleted At: ".($hasDeletedAt ? 'true': 'false'), $onlyVerboseLevel);

        if (empty($attributes)) {
            $this->info("\tAttributes:     none", $onlyVerboseLevel);
        } else {
            $this->info("\tAttributes:", $onlyVerboseLevel);
            foreach ($attributes as $attribute) {
                $this->info("\t\t\t".$attribute->name." (".$attribute->type.")", $onlyVerboseLevel);
            }
        }

        $parsingKeys = array();
        $hasCollision = false;
        if (empty($foreignKeys)) {
            $this->info("\tForeign Keys:   none", $onlyVerboseLevel);
        } else {
            $this->info("\tForeign Keys:", $onlyVerboseLevel);
            foreach ($foreignKeys as $foreignKey) {
                if (in_array($foreignKey->name, $parsingKeys)) {
                    $hasCollision = true;
                    $this->info("\t\t".$foreignKey->name.' !!! COLLISION DETECTED !!!', $onlyVerboseLevel);
                } else {
                    $this->info("\t\t".$foreignKey->name, $onlyVerboseLevel);
                }
                $parsingKeys[] = $foreignKey->name;

                $this->info("\t\t\tType:   ".$foreignKey->type, $onlyVerboseLevel);
                $this->info("\t\t\tTarget: ".$foreignKey->model, $onlyVerboseLevel);
                if ($foreignKey->type === 'belongsToMany') {
                    $this->info("\t\t\tThrew:  ".$foreignKey->table.' ('.$foreignKey->foreign_key.', '.
                        $foreignKey->other_key.')', $onlyVerboseLevel);
                } else if ($foreignKey->type === 'belongsTo') {
                    $this->info("\t\t\tLink:   ".$foreignKey->foreign_key.' to '.$foreignKey->other_key.' ('.
                        $foreignKey->table.')', $onlyVerboseLevel);
                } else {
                    $this->info("\t\t\tLink:   ".$foreignKey->other_key.' to '.$foreignKey->foreign_key.' ('.
                        $foreignKey->table.')', $onlyVerboseLevel);
                }
            }
        }

        if (empty($timestamps)) {
            $this->info("\tDates:          []", $onlyVerboseLevel);
        } else {
            $this->info("\tDates:          ['".implode('\', \'', $timestamps).'\']', $onlyVerboseLevel);
        }

        if (empty($fillables)) {
            $this->info("\tFillables:      []", $onlyVerboseLevel);
        } else {
            $this->info("\tFillables:      ['".implode('\', \'', $fillables).'\']', $onlyVerboseLevel);
        }

        if (empty($traits)) {
            $this->info("\tTraits:         none", $onlyVerboseLevel);
        } else {
            $this->info("\tTraits:", $onlyVerboseLevel);
            foreach ($traits as $trait) {
                $this->info("\t\t\t".$trait->name." (".$trait->use.")", $onlyVerboseLevel);
            }
        }

        if (empty($scopes)) {
            $this->info("\tScopes:         none", $onlyVerboseLevel);
        } else {
            $this->info("\tScopes:", $onlyVerboseLevel);
            foreach ($scopes as $scope) {
                if (isset($scope->variable) && !empty($scope->variable)) {
                    $this->info("\t\t\t\"".$scope->name.'" '.$scope->symbol.' '.$scope->variable, $onlyVerboseLevel);
                } else if (isset($scope->value) && !empty($scope->value)) {
                    $this->info("\t\t\t\"".$scope->name.'" '.$scope->symbol.' '.$scope->value, $onlyVerboseLevel);
                } else {
                    $this->info("\t\t\t\"".$scope->name.'" '.$scope->symbol.' $'.$scope->name, $onlyVerboseLevel);
                }

            }
        }

        if ($hasCollision) {
            $this->info('');
            if ($this->isVerbose()) {
                $this->info('One or more collision have been detected, check above for exact details');
            } else {
                $this->info('One or more collision have been detected, use --verbose to get more details');
            }
            $this->info('');
        }

        /*
         * --------------------------
         *   RENDERING
         * --------------------------
         */
        $result = view($template, array(
            'namespace'   => $namespace,
            'name'        => $name,
            'table'       => $table,
            'description' => $description,

            'hasCreatedAt' => $hasCreatedAt,
            'hasUpdatedAt' => $hasUpdatedAt,
            'hasDeletedAt' => $hasDeletedAt,

            'attributes' => $attributes,
            'extends'    => $extends,
            'fillables'  => $fillables,
            'dates'      => $timestamps,

            'foreignKeys' => $foreignKeys,

            'scopes' => $scopes,
            'traits' => $traits
        ));

        // Sanitize the output
        $result = $this->prettyOutput($result);

        /*
         * --------------------------
         *   OUTPUT
         * --------------------------
         */
        // Creating the folder
        if (!file_exists($folder)) {
            mkdir($folder, 0755, true);
        }
        // Storing data
        $filename = $folder.DIRECTORY_SEPARATOR.$name.'.php';
        file_put_contents($filename, $result);

        $this->info('Model created: '.$name.' (table: "'.$table.'")');

        /*
         * --------------------------
         *   SANITIZE
         * --------------------------
         */
        $phpcbf = base_path('vendor/bin/phpcbf');

        if (file_exists($phpcbf)) {
            $this->info('Found PHPCBF, sanitize (PSR2 compliance)', $onlyVerboseLevel);

            $process = new Process($phpcbf.' --standard=PSR2 --no-patch "'.$filename.'"');
            $process->run();
            echo $process->getOutput();

            $this->info('PSR2 compliance done', $onlyVerboseLevel);
        } else {
            $this->info('PHPCBF not found, skipping sanitize', $onlyVerboseLevel);
        }
    }
}
