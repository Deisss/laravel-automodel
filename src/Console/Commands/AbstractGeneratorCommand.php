<?php

namespace Deisss\Automodel\Console\Commands;

use DB;
use Config;
use Illuminate\View\Factory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Blade;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Common base for generating model template.
 *
 * Class AbstractGeneratorCommand
 * @package Deisss\Automodel\Console\Commands
 */
abstract class AbstractGeneratorCommand extends Command
{
    /*
     * ------------------------------------------
     *   VARIABLES
     * ------------------------------------------
     */
    /**
     * Tables that are skipped by default.
     *
     * @var array
     */
    protected $skipped = array(
        'password_resets',
        'migrations'
    );

    /**
     * Elements that are non-fillable by default.
     *
     * @var array
     */
    protected $nofill = array(
        'id',
        'deleted_at'
    );

    /**
     * List of MySQL types that are "integer" type in PHP.
     *
     * @var array
     */
    protected $integer = array(
        'bit',
        'tinyint',
        'smallint',
        'mediumint',
        'int',
        'bigint'
    );

    /**
     * List of MySQL types that are "double" type in PHP.
     *
     * @var array
     */
    protected $double = array(
        'decimal',
        'numeric',
        'float',
        'double'
    );

    /**
     * List of MySQL types that are "boolean" type in PHP.
     *
     * @var array
     */
    protected $boolean = array(
        'bool',
        'boolean'
    );

    /**
     * List of MySQL types that are "date" type in PHP.
     *
     * @var array
     */
    protected $date = array(
        'date',
        'datetime',
        'time',
        'timestamp',
        'year'
    );

    /*
     * ------------------------------------------
     *   HELPERS - OUTPUT
     * ------------------------------------------
     */
    /**
     * Get if we are in verbose mode or not.
     *
     * @return bool The verbose state, true it's verbose, false it's not.
     */
    protected function isVerbose()
    {
        return ($this->getOutput()->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE);
    }

    /**
     * Print a nice title.
     *
     * @param string $title The title content.
     * @param integer|null $verbosity The verbosity level to start printing it.
     */
    protected function title($title, $verbosity = OutputInterface::VERBOSITY_NORMAL)
    {
        $this->info('', $verbosity);
        $this->info("\033[32m***************************************\033[0m", $verbosity);
        $this->info(" [\033[32mCOMMAND\033[0m] ".strtoupper($title), $verbosity);
        $this->info("\033[32m***************************************\033[0m", $verbosity);
        $this->info('', $verbosity);
    }

    /*
     * ------------------------------------------
     *   HELPERS - GENERAL
     * ------------------------------------------
     */
    /**
     * Get the schema filename.
     *
     * @return string The schema filename
     */
    protected function getSchemaFilename()
    {
        return base_path('.schema');
    }

    /**
     * Get the schema content (an empty array if there is a problem).
     *
     * @return array The content array.
     * @throws \Exception In case of reading problem this function will raise an exception.
     */
    protected function getSchemaContent()
    {
        $filename = $this->getSchemaFilename();

        if (file_exists($filename)) {
            $existing = json_decode(file_get_contents($filename), true);

            switch (json_last_error()) {
                case JSON_ERROR_NONE:
                    return (!empty($existing)) ? $existing: array();
                case JSON_ERROR_DEPTH:
                    throw new \Exception('JSON error - Maximum depth reached');
                case JSON_ERROR_STATE_MISMATCH:
                    throw new \Exception('JSON error - State mismatch');
                case JSON_ERROR_CTRL_CHAR:
                    throw new \Exception('JSON error - Characters control error');
                case JSON_ERROR_SYNTAX:
                    throw new \Exception('JSON error - Malformed JSON');
                case JSON_ERROR_UTF8:
                    throw new \Exception('JSON error - UTF8 problem');
                default:
                    throw new \Exception('JSON error - Unknown exception');
            }
        }

        return [];
    }

    /**
     * Convert a class name into a function name (for rolling back to a "plurial" version of a class name).
     *
     * @param string $class The class name to convert.
     * @param bool $singular
     * @return string
     */
    protected function classToFunction($class, $singular = true)
    {
        if (!$singular) {
            $split = preg_split('/(?=[A-Z])/', $class, -1, PREG_SPLIT_NO_EMPTY);

            if (!empty($split)) {
                $max = count($split) - 1;
                $split[$max] = ucfirst($this->pluralize($split[$max]));
                return lcfirst(implode('', $split));
            }
        }

        // A problem occurs, or we are in singular mode.
        return ($singular)? lcfirst($class): lcfirst($this->pluralize($class));
    }

    /**
     * Return the attended class name related to table name.
     *
     * @param string $table The table name to find related class name.
     * @param boolean $singular If we should set as singular or not the name (default: true).
     * @param boolean $useSchemaFile If we search into the schema file or not (default: true).
     * @return string The class name found.
     */
    protected function tableToClass($table, $singular = true, $useSchemaFile = true)
    {
        // Searching first into our schema file
        if ($useSchemaFile) {
            $schema = $this->getSchemaContent();
            foreach ($schema as $item) {
                if ($item['table'] === $table) {
                    return $item['name'];
                }
            }
        }

        // Going for the "normal deduct" mode
        $split = preg_split('/\s+/', str_replace('_', ' ', $table));
        if (!empty($split)) {
            $max = count($split) - 1;

            if ($singular) {
                $split[$max] = $this->singularize($split[$max]);
            }
        }

        if (!empty($split)) {
            for ($i = 0, $l = count($split); $i < $l; $i++) {
                $split[$i] = ucwords($split[$i]);
            }

            // Merging everything
            return implode('', $split);
        }

        return 'ERROR';
    }


    /**
     * From a table name, get the related model (which is the table class + the namespace).
     *
     * @param string $table The table to find related model
     * @return string The related model class
     */
    protected function tableToModel($table)
    {
        // Trying to find the right model name in the schema file.
        $schema = $this->getSchemaContent();
        foreach ($schema as $item) {
            if ($item['table'] === $table) {
                return $item['namespace'].'\\'.$item['name'];
            }
        }

        // In this case we go for the more "common" version
        return 'App\\Models\\'.$this->tableToClass($table, true, true);
    }

    /**
     * Get the namespace related to a given table, or generate a default one.
     *
     * @param string $table The table to get related namespace.
     * @return string The namespace found/generated.
     */
    protected function tableToNamespace($table)
    {
        // Trying to find the right model name in the schema file.
        $schema = $this->getSchemaContent();
        foreach ($schema as $item) {
            if ($item['table'] === $table && isset($item['namespace']) && !empty($item['namespace'])) {
                return $item['namespace'];
            }
        }

        return 'App\\Models';
    }

    /**
     * Get the folder related to a given table, or generate a default one.
     *
     * @param string $table The table to get related folder.
     * @return string The folder found/generated.
     */
    protected function tableToFolder($table)
    {
        // Trying to find the right model name in the schema file.
        $schema = $this->getSchemaContent();
        foreach ($schema as $item) {
            if ($item['table'] === $table && isset($item['folder']) && !empty($item['folder'])) {
                return $item['folder'];
            }
        }

        return 'app'.DIRECTORY_SEPARATOR.'Models';
    }

    /**
     * Get the template related to a given table, or generate a default one.
     *
     * @param string $table The table to get related template.
     * @return string The template found/generated.
     */
    protected function tableToTemplate($table)
    {
        // Trying to find the right model name in the schema file.
        $schema = $this->getSchemaContent();
        foreach ($schema as $item) {
            if ($item['table'] === $table && isset($item['template']) && !empty($item['template'])) {
                return $item['template'];
            }
        }

        $DS = DIRECTORY_SEPARATOR;
        return __DIR__.$DS.'..'.$DS.'..'.$DS.'resources'.$DS.'views'.$DS.'models'.DIRECTORY_SEPARATOR.
            'model.blade.php';
    }


    /**
     * Convert from plural into singular.
     *
     * @param string $name The input to convert
     * @return string The singular version of the input
     */
    protected function singularize($name)
    {
        $name = strtolower($name);

        // Based on this grammar rules: http://www.ef.com/english-resources/english-grammar/singular-and-plural-nouns/
        $lastOne   = substr($name, -1);
        $lastThree = substr($name, -3);
        $lastFour  = substr($name, -4);
        $lastFive  = substr($name, -5);

        $vowels = array('a', 'e', 'i', 'o', 'u', 'y');

        // Taken from: http://english-zone.com/spelling/plurals.html
        $exceptions = array(
            // I => US
            'alumni' => 'alumnus',
            'cacti' => 'cactus',
            'foci' => 'focus',
            'fungi' => 'fungus',
            'nuclei' => 'nucleus',
            'radii' => 'radius',
            'stimuli' => 'stimulus',

            // ES => IS
            'axes' => 'axis',
            'analyses' => 'analysis',
            'bases' => 'basis',
            'crises' => 'crisis',
            'diagnoses' => 'diagnosis',
            'ellipses' => 'ellipsis',
            'hypotheses' => 'hypothesis',
            'oases' => 'oasis',
            'paralyses' => 'paralysis',
            'parentheses' => 'parenthesis',
            'syntheses' => 'synthesis',
            'synopses' => 'synopsis',
            'theses' => 'thesis',

            // ICES => IX
            'appendices' => 'appendix',
            'indeces' => 'index',
            'matrices' => 'matrix',

            // EAU => EAUX
            'beaux' => 'beau',
            'bureaux' => 'bureau',
            'bureaus' => 'bureau',
            'tableaux' => 'tableau',
            'tableaus' => 'tableau',

            // EN => ***
            'children' => 'child',
            'men' => 'man',
            'oxen' => 'ox',
            'women' => 'woman',

            // A => ***
            'bacteria' => 'bacterium',
            'corpora' => 'corpus',
            'criteria' => 'criterion',
            'curricula' => 'curriculum',
            'data' => 'datum',
            'genera' => 'genus',
            'media' => 'medium',
            'memoranda' => 'memorandum',
            'phenomena' => 'phenomenon',
            'strata' => 'stratum',

            // NO CHANGE
            'bison' => 'bison',
            'cod' => 'cod',
            'pike' => 'pike',
            'salmon' => 'salmon',
            'shrimp' => 'shrimp',
            'swine' => 'swine',
            'trout' => 'trout',
            'deer' => 'deer',
            'fish' => 'fish',
            'means' => 'means',
            'offspring' => 'offspring',
            'series' => 'series',
            'sheep' => 'sheep',
            'species' => 'species',
            'news' => 'news',
            'information' => 'information',

            // EE => OO
            'feet' => 'foot',
            'geese' => 'goose',
            'teeth' => 'tooth',

            // AE => A
            'antennae' => 'antenna',
            'formulae' => 'formula',
            'nebulae' => 'nebula',
            'vertebrae' => 'vertebra',
            'vitae' => 'vita',

            // ICE => OUSE
            'lice' => 'louse',
            'mice' => 'mouse',

            // OTHERS
            'leaves' => 'leaf',
            'halves' => 'half',
            'knives' => 'knife',
            'wives' => 'wife',
            'lives' => 'life',
            'elves' => 'elf',
            'loaves' => 'loaf',
            'potatoes' => 'potato',
            'tomatoes' => 'tomato',
            'syllabi' => 'syllabus'
        );

        // Exceptions
        if (array_key_exists($name, $exceptions)) {
            return $exceptions[$name];
        }

        // Craft does not vary
        if ($lastFive === 'craft') {
            return $name;
        }

        // s => ses, x => xes, z => zes
        // ch => ches, sh => shes
        if ($lastThree === 'ses' || $lastThree === 'xes' || $lastThree === 'zes' || $lastFour === 'ches' || $lastFour === 'shes') {
            return substr($name, 0, -2);
        }

        // y => ies, does not work if there is a vowel just before the "y" (in this case it switch to the basic case)
        if ($lastThree === 'ies' && !in_array(substr($lastFour, 0, 1), $vowels)) {
            return substr($name, 0, -3).'y';
        }

        //

        // => s, The most simple case
        if ($lastOne === 's') {
            return substr($name, 0, -1);
        }

        // Should never reach that point but just in case...
        return $name;
    }

    /**
     * Convert from singular to plural.
     *
     * @param string $name The name to convert.
     * @return string The converted name.
     */
    protected function pluralize($name)
    {
        $name = strtolower($name);

        // Based on this grammar rules: http://www.ef.com/english-resources/english-grammar/singular-and-plural-nouns/
        $lastOne   = substr($name, -1);
        $lastTwo   = substr($name, -2);
        $lastFive  = substr($name, -5);

        $vowels = array('a', 'e', 'i', 'o', 'u', 'y');

        $exceptions = array(
            // US => I
            'alumnus' => 'alumni',
            'cactus' => 'cacti',
            'focus' => 'foci',
            'fungus' => 'fungi',
            'nucleus' => 'nuclei',
            'radius' => 'radii',
            'stimulus' => 'stimuli',

            // IS => ES
            'axis' => 'axes',
            'analysis' => 'analyses',
            'basis' => 'bases',
            'crisis' => 'crises',
            'diagnosis' => 'diagnoses',
            'ellipsis' => 'ellipses',
            'hypothesis' => 'hypotheses',
            'oasis' => 'oases',
            'paralysis' => 'paralyses',
            'parenthesis' => 'parentheses',
            'synthesis' => 'syntheses',
            'synopsis' => 'synopses',
            'thesis' => 'theses',

            // IX => ICES
            'appendix' => 'appendices',
            'index' => 'indeces',
            'matrix' => 'matrices',

            // EAU => EAUX
            'beau' => 'beaux',
            'bureau' => 'bureaux',
            'tableau' => 'tableaux',

            // *** => EN
            'child' => 'children',
            'man' => 'men',
            'ox' => 'oxen',
            'woman' => 'women',

            // *** => A
            'bacterium' => 'bacteria',
            'corpus' => 'corpora',
            'criterion' => 'criteria',
            'curriculum' => 'curricula',
            'datum' => 'data',
            'genus' => 'genera',
            'medium' => 'media',
            'memorandum' => 'memoranda',
            'phenomenon' => 'phenomena',
            'stratum' => 'strata',

            // NO CHANGE
            'bison' => 'bison',
            'cod' => 'cod',
            'pike' => 'pike',
            'salmon' => 'salmon',
            'shrimp' => 'shrimp',
            'swine' => 'swine',
            'trout' => 'trout',
            'deer' => 'deer',
            'fish' => 'fish',
            'means' => 'means',
            'offspring' => 'offspring',
            'series' => 'series',
            'sheep' => 'sheep',
            'species' => 'species',
            'news' => 'news',
            'information' => 'information',

            // OO => EE
            'foot' => 'feet',
            'goose' => 'geese',
            'tooth' => 'teeth',

            // A => AE
            'antenna' => 'antennae',
            'formula' => 'formulae',
            'nebula' => 'nebulae',
            'vertebra' => 'vertebrae',
            'vita' => 'vitae',

            // OUSE => ICE
            'louse' => 'lice',
            'mouse' => 'mice',

            // OTHERS
            'leaf' => 'leaves',
            'half' => 'halves',
            'knife' => 'knives',
            'wife' => 'wives',
            'life' => 'lives',
            'elf' => 'elves',
            'loaf' => 'loaves',
            'potato' => 'potatoes',
            'tomato' => 'tomatoes',
            'syllabus' => 'syllabi'
        );

        // Exceptions
        if (array_key_exists($name, $exceptions)) {
            return $exceptions[$name];
        }

        // Craft does not vary
        if ($lastFive === 'craft') {
            return $name;
        }

        // s => ses, x => xes, z => zes
        // ch => ches, sh => shes
        if (in_array($lastOne, array('s', 'x', 'z', 'ch', 'sh'))) {
            return $name.'es';
        }

        // y => ies, does not work if there is a vowel just before the "y" (in this case it switch to the basic case)
        if ($lastOne === 'y' && !in_array(substr($lastTwo, 0, 1), $vowels)) {
            return substr($name, 0, -1).'ies';
        }

        return $name.'s';
    }


    /*
     * ------------------------------------------
     *   HELPERS - SQL
     * ------------------------------------------
     */
    /**
     * Convert all the columns into PHP version. This is mostly used for documentation with PHPStorm or something
     * equivalent.
     *
     * @param array $columns The columns to convert.
     * @return array The converted array.
     */
    protected function convertSQLVariableTypesToPHPVariableTypes($columns)
    {
        $results = array();

        foreach ($columns as $column) {
            // By default, we consider everything as "string" type unless we found better type.
            $type = 'string';

            if (in_array($column->type, $this->integer)) {
                $type = 'integer';
            } else if (in_array($column->type, $this->boolean)) {
                $type = 'boolean';
            } else if (in_array($column->type, $this->double)) {
                $type = 'double';
            } else if (in_array($column->type, $this->date)) {
                $type = '\Carbon\Carbon';
            }

            $result = new \stdClass();
            $result->name = $column->name;
            $result->type = $type;

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Get the database name in use for this instance of laravel.
     *
     * @return string The database name in use.
     */
    protected function getDatabase()
    {
        return Config::get('database.connections.'.Config::get('database.default').'.database');
    }

    /**
     * Check given table exists in the MySQL database (and you got the right to access it).
     *
     * @param string $database The database to check table inside.
     * @param string $table The table name to check.
     * @return bool True if it exists, false otherwise.
     */
    protected function tableExists($database, $table)
    {
        $result = DB::select("SELECT COUNT(*) AS `c` FROM `INFORMATION_SCHEMA`.`TABLES` WHERE `TABLE_SCHEMA` = '".
            $database."' AND `TABLE_NAME` = '".$table."'");
        return ($result[0]->c > 0);
    }

    /**
     * Get all tables associated to given database.
     *
     * @param string $database The database to search related tables.
     * @return array The tables found.
     */
    protected function getTables($database)
    {
        return DB::select('SELECT `TABLE_NAME` AS `name` FROM `INFORMATION_SCHEMA`.`TABLES` WHERE
            `TABLE_SCHEMA` = "'.$database.'"');
    }

    /**
     * Try to detect if a given table is a pivot table or not.
     * A pivot table is represented by: multiple primary key, and no "ID" key.
     *
     * @param string $database The database where table belongs to.
     * @param string $table The table to search primary keys in.
     * @return True if it's a pivot table, false otherwise.
     */
    protected function isPivotTable($database, $table)
    {
        $primaryKeys = DB::select('SELECT `COLUMN_NAME` AS `name` FROM `INFORMATION_SCHEMA`.`KEY_COLUMN_USAGE` WHERE 
            `TABLE_SCHEMA` = "'.$database.'" AND `TABLE_NAME` = "'.$table.'" AND `CONSTRAINT_NAME` = "PRIMARY"');

        $foundId = false;
        foreach ($primaryKeys as $primaryKey) {
            if (strtolower($primaryKey->name) === 'id') {
                $foundId = true;
                break;
            }
        }

        return ($foundId === false && count($primaryKeys) > 1);
    }

    /**
     * Get all the keys associated to the table (primary, "internal" foreign keys and "external" foreign keys). Please
     * note this function may return some duplicates that needs to be filtered...
     * The system will returns first of all the
     *
     * @param string $database The database to check table inside.
     * @param string $table The table to check keys inside.
     * @return array A collection of elements.
     */
    protected function getTableKeys($database, $table)
    {
        return DB::select('SELECT `TABLE_NAME` AS `table_name`, `COLUMN_NAME` AS `column_name`, 
                `REFERENCED_TABLE_NAME` AS `referenced_table_name`, `REFERENCED_COLUMN_NAME` AS `referenced_column_name`,
	            "primary" AS `type` FROM `INFORMATION_SCHEMA`.`KEY_COLUMN_USAGE` WHERE `TABLE_SCHEMA` = "'.$database.'" 
	            AND `TABLE_NAME` = "'.$table.'" AND `CONSTRAINT_NAME` = "PRIMARY"
            UNION
	        SELECT `TABLE_NAME` AS `table_name`, `COLUMN_NAME` AS `column_name`, 
	            `REFERENCED_TABLE_NAME` AS `referenced_table_name`, `REFERENCED_COLUMN_NAME` AS `referenced_column_name`,
	            "external_foreign_key" AS `type` FROM `INFORMATION_SCHEMA`.`KEY_COLUMN_USAGE` WHERE 
	            `REFERENCED_TABLE_SCHEMA` = "'.$database.'" AND `REFERENCED_TABLE_NAME` = "'.$table.'"
	        UNION
	        SELECT `TABLE_NAME` AS `table_name`, `COLUMN_NAME` AS `column_name`,
	            `REFERENCED_TABLE_NAME` AS `referenced_table_name`, `REFERENCED_COLUMN_NAME` AS `referenced_column_name`,
	            "internal_foreign_key" AS `type` FROM `INFORMATION_SCHEMA`.`KEY_COLUMN_USAGE` WHERE
	            `TABLE_SCHEMA` = "'.$database.'" AND `TABLE_NAME` = "'.$table.'" AND `REFERENCED_TABLE_NAME` IS NOT NULL');
    }

    /**
     * Get all columns available for the given table.
     *
     * @param string $database The database to look into.
     * @param string $table The table to look into.
     * @return array The list of columns found.
     */
    protected function getTableColumns($database, $table)
    {
        return DB::select('SELECT `COLUMN_NAME` AS `name`, `DATA_TYPE` AS `type` FROM `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE `TABLE_SCHEMA`="'.$database.'" AND `TABLE_NAME`="'.$table.'"');
    }

    /**
     * Get the description associated to a table.
     *
     * @param string $database The database to search related comment.
     * @param string $table The table to search related comment.
     * @return string The comment found.
     */
    protected function getTableDescription($database, $table)
    {
        $result = DB::select('SELECT `TABLE_COMMENT` AS `comment` FROM `INFORMATION_SCHEMA`.`TABLES` WHERE
            `TABLE_SCHEMA` = "'.$database.'" AND `TABLE_NAME` = "'.$table.'"');
        if (empty($result)) {
            return '';
        }
        return $result[0]->comment ?: '';
    }

    /**
     * Detect if, on the given table and column there is a unique constraint or not.
     *
     * @param string $database The database to search for.
     * @param string $table The table to search for.
     * @param string $column The column to search unique constraint.
     * @return bool True if it's a unique's marked column, false otherwise
     */
    protected function isTableColumnIsUnique($database, $table, $column)
    {
        $result = DB::select('SELECT `CONSTRAINT_NAME` FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE
            `TABLE_SCHEMA` = "'.$database.'" AND `TABLE_NAME` = "'.$table.'" AND `CONSTRAINT_TYPE` = "UNIQUE" AND
            `CONSTRAINT_NAME` IN (SELECT `CONSTRAINT_NAME` FROM `information_schema`.`KEY_COLUMN_USAGE` WHERE
            `TABLE_SCHEMA` = "'.$database.'" AND `TABLE_NAME` = "'.$table.'" AND `COLUMN_NAME` = "'.$column.
            '" AND `REFERENCED_TABLE_SCHEMA` IS NULL AND `REFERENCED_TABLE_NAME` IS NULL) LIMIT 0, 1');
        if (empty($result)) {
            return false;
        }
        return !empty($result[0]->CONSTRAINT_NAME);
    }

    /**
     * Get target pivot table (the other table that create this pivot table).
     *
     * @param string $database The database we are searching on.
     * @param string $table The pivot table where we start to search.
     * @param string $column The pivot column where WE CAME FROM.
     * @return mixed What we have found (can be null).
     */
    protected function getTargetPivotTable($database, $table, $column)
    {
        // We search for key that are
        $result = DB::select('SELECT `REFERENCED_TABLE_NAME` AS `table_name`, `REFERENCED_COLUMN_NAME` AS `column_name`,
            `COLUMN_NAME` AS `referenced_column_name` FROM `INFORMATION_SCHEMA`.`KEY_COLUMN_USAGE`
            WHERE `TABLE_SCHEMA` = "'.$database.'" AND `TABLE_NAME` = "'.$table.'" AND
            `COLUMN_NAME` <> "'.$column.'" AND `REFERENCED_TABLE_NAME` IS NOT NULL AND
            `COLUMN_NAME` IN (SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`KEY_COLUMN_USAGE`
            WHERE `TABLE_SCHEMA` = "'.$database.'" AND `TABLE_NAME` = "'.$table.'" AND
            `CONSTRAINT_NAME` = "PRIMARY")');

        if (count($result) === 1) {
            return $result[0];
        }

        // In case of problem (like we found two keys) we get back a null value that will be catch by code later.
        return null;
    }
}