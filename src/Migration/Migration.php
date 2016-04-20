<?php
/**
 * Peanut\Migration
 *
 * @package    Peanut\Migration
 */
namespace Peanut\Migration;

/**
 * Migration Class
 *
 * @author kohkimakimoto <kohki.makimoto@gmail.com>
 * @author max <kwon@yejune.com>
 */
class Migration
{

    protected $config;
    protected $logger;
    protected $conns = array();
    public $migration_dir = 'migrations';

    public function __construct($config = array())
    {
        $this->config        = new Config($config);
        $this->migration_dir = 'migrations';
        $this->version_path  = $this->migration_dir.'/.migration';
        $this->config_file   = $this->migration_dir.'/.migration/config.php';
        $this->config->merge(array_merge(include_once $this->config_file, $this->config->getAll()));

        $this->logger        = new Logger($this->config);
    }

    /**
     * Run Helps Command
     */
    public function helpForCli()
    {
        $this->logger->write("LibMigration is a minimum database migration library and framework for MySQL. version ".self::VERSION);
        $this->logger->write("");
        $this->logger->write("Copyright (c) Kohki Makimoto <kohki.makimoto@gmail.com>");
        $this->logger->write("Apache License 2.0");
        $this->logger->write("");
        $this->logger->write("Usage");
        $this->logger->write("  phpmigrate [-h|-d|-c] COMMAND");
        $this->logger->write("");
        $this->logger->write("Options:");
        $this->logger->write("  -d         : Switch the debug mode to output log on the debug level.");
        $this->logger->write("  -h         : List available command line options (this page).");
        $this->logger->write("  -f=FILE    : Specify to load configuration file.");
        $this->logger->write("  -c         : List configurations.");
        $this->logger->write("");
        $this->logger->write("Commands:");
        $this->logger->write("  create NAME [DATABASENAME ...]    : Create new skeleton migration task file.");
        $this->logger->write("  status [DATABASENAME ...]         : List the migrations yet to be executed.");
        $this->logger->write("  migrate [DATABASENAME ...]        : Execute the next migrations up.");
        $this->logger->write("  up [DATABASENAME ...]             : Execute the next migration up.");
        $this->logger->write("  down [DATABASENAME ...]           : Execute the next migration down.");
        $this->logger->write("  init                              : Create skelton configuration file in the current working directory.");
        $this->logger->write("");
    }

    /**
     * List config
     */
    public function listConfig()
    {
        $largestLength = Utils::arrayKeyLargestLength($this->config->getAllOnFlatArray());
        $this->logger->write("");
        $this->logger->write("Configurations :");
        foreach ($this->config->getAllOnFlatArray() as $key => $val)
        {
            if ($largestLength === strlen($key))
            {
                $sepalator = str_repeat(" ", 0);
            }
            else
            {
                $sepalator = str_repeat(" ", $largestLength - strlen($key));
            }

            $message = "  [".$key."] ".$sepalator;
            if (true === is_array($val))
            {
                $message .= "=> array()";
            }
            else
            {
                $message .= "=> ".$val;
            }
            $this->logger->write($message);
        }
        $this->logger->write("");
    }

    /**
     * Run Create Command
     */
    public function create($taskName, $databases = array())
    {
        $databases = $this->getDatabaseNames($databases);

        $timestamp = new \DateTime();
        foreach ($databases as $database)
        {
            $this->createMigrationTask($taskName, $timestamp, $database);
        }
    }

    /**
     * Run Status Command
     * @param array $databases
     */
    public function status($databases = array())
    {
        $this->checkAllMigrationFileList();

        $databases = $this->getDatabaseNames($databases);

        foreach ($databases as $database)
        {
            $version = $this->getSchemaVersion($database);

            $files = $this->getValidMigrationUpFileList($database, $version);
            if (0 === count($files))
            {
                $this->logger->write("Already up to date.", "[$database]");
                continue;
            }

            $this->logger->write("Your migrations yet to be executed are below.", "[$database]");
            $this->logger->write("");
            foreach ($files as $file)
            {
                $this->logger->write(basename($file));
            }
            $this->logger->write("");
        }
    }

    /**
     * Run Migrate Command
     * @param unknown $databases
     */
    public function migrate($databases = array())
    {
        $this->checkAllMigrationFileList();

        $databases = $this->getDatabaseNames($databases);

        foreach ($databases as $database)
        {
            $version = $this->getSchemaVersion($database);

            $files = $this->getValidMigrationUpFileList($database, $version);
            if (0 === count($files))
            {
                $this->logger->write("Already up to date.", "[$database]");
                continue;
            }

            foreach ($files as $file)
            {
                $this->migrateUp($file, $database);
            }
        }
    }

    /**
     * Run Up Command
     * @param unknown $databases
     */
    public function up($databases = array())
    {
        $this->checkAllMigrationFileList();

        $databases = $this->getDatabaseNames($databases);

        foreach ($databases as $database)
        {
            $version = $this->getSchemaVersion($database);

            $files = $this->getValidMigrationUpFileList($database, $version);
            if (0 === count($files))
            {
                $this->logger->write("Already up to date.", "[$database]");
                continue;
            }

            $this->migrateUp($files[0], $database);
        }
    }

    /**
     * Run Down Command
     * @param unknown $databases
     */
    public function down($databases = array())
    {
        $this->checkAllMigrationFileList();

        $databases = $this->getDatabaseNames($databases);

        foreach ($databases as $database)
        {
            $version = $this->getSchemaVersion($database);

            $files = $this->getValidMigrationDownFileList($database, $version);
            if (0 === count($files))
            {
                $this->logger->write("Not found older migration files than current schema version.", "[$database]");
                continue;
            }

            $prev_version = null;
            if (true === isset($files[1]))
            {
                preg_match("/(\d+)_(.*)\.php$/", basename($files[1]), $matches);
                $prev_version    = $matches[1];
            }

            $this->migrateDown($files[0], $prev_version, $database);
        }

    }

    /**
     * Init task creates skelton configuration file.
     * @throws Exception
     */
    public function init()
    {
        $cwd = getcwd();
        $configpath = $cwd.'/'.$this->config_file;
        if (true === file_exists($configpath))
        {
            throw new Exception("Exists $configpath");
        }

        if (false === is_dir(dirname($configpath)))
        {
            mkdir(dirname($configpath), 0777, true);
        }

        $content = <<<END
<?php
return array(
    'colors' => true,
    'databases' => array(
        'master' => array(
            // PDO Connection settings.
            'dsn'      => 'mysql:dbname=yourdatabase;host=localhost',
            'user'     => 'user',
            'password' => 'password',
        ),
    ),
);

END;

        file_put_contents($configpath, $content);
        $this->logger->write("Create configuration file to $configpath");
    }

    public function getConfig()
    {
        return $this->config;
    }

    protected function createMigrationTask($taskName, $timestamp, $database)
    {
        $taskName = $database.'_'.$taskName;
        $taskName = Utils::underscore($taskName);
        $filename = $timestamp->format('YmdHis')."_".$taskName.".php";
        $filepath = $this->migration_dir."/".$filename;
        $camelizeName = Utils::camelize($taskName);

        $gfiles = glob($this->migration_dir.'/*');

        foreach ($gfiles as $file)
        {
            if (1 === preg_match("/^\d+_.+\.php$/", basename($file)))
            {
                preg_match("/(\d+)_(.*)\.php$/", basename($file), $matches);
                $version    = $matches[1];
                $className = Utils::camelize($matches[2]);

                // Check to exist same class name.
                if ($className === $camelizeName)
                {
                    // Can't use same class name to migration tasks.
                    throw new Exception("Can't use same class name to migration tasks. Duplicate migration task name [".$className."] and [".$file."].");
                }
            }
        }

        $content = <<<EOF
<?php
/**
 * Migration Task class.
 */
class $camelizeName
{
    public function preUp($conn)
    {
        // add the pre-migration code here
    }

    public function postUp($conn)
    {
        // add the post-migration code here
    }

    public function preDown($conn)
    {
        // add the pre-migration code here
    }

    public function postDown($conn)
    {
        // add the post-migration code here
    }

    /**
     * Return the SQL statements for the Up migration
     *
     * @return string The SQL string to execute for the Up migration.
     */
    public function up($conn)
    {
         $conn->exec(<<<END

END);
    }

    /**
     * Return the SQL statements for the Down migration
     *
     * @return string The SQL string to execute for the Down migration.
     */
    public function down($conn)
    {
         $conn->exec(<<<END

END);
    }

}
EOF;
        if (false === is_dir(dirname($filepath)))
        {
            mkdir(dirname($filepath), 0777, true);
        }

        file_put_contents($filepath, $content);

        $this->logger->write("Created ".$filepath, "[$database]");
    }

    protected function migrateUp($file, $database)
    {
        $this->logger->write("Processing migrate up by ".basename($file)."", "[$database]");

        include_once $file;

        preg_match("/(\d+)_(.*)\.php$/", basename($file), $matches);
        $version    = $matches[1];
        $className = Utils::camelize($matches[2]);

        $migrationInstance = new $className();

        $conn = $this->getConnection($database);

        if (true === method_exists($migrationInstance, 'preUp'))
        {
            $migrationInstance->preUp($conn);
        }

        $migrationInstance->up($conn);

        if (true === method_exists($migrationInstance, 'postUp'))
        {
            $migrationInstance->postUp($conn);
        }

        $this->updateSchemaVersion($version, $database);
    }

    protected function migrateDown($file, $prev_version, $database)
    {
        if(null === $prev_version)
        {
            $this->logger->write("Processing migrate down to version initialization by ".basename($file)."", "[$database]");
        }
        else
        {
            $this->logger->write("Processing migrate down to version $prev_version by ".basename($file)."", "[$database]");
        }

        include_once $file;

        preg_match("/(\d+)_(.*)\.php$/", basename($file), $matches);
        $version    = $matches[1];
        $className = Utils::camelize($matches[2]);

        $migrationInstance = new $className();

        $conn = $this->getConnection($database);

        if (true === method_exists($migrationInstance, 'preDown'))
        {
            $migrationInstance->preDown($conn);
        }

        $sql = $migrationInstance->down($conn);

        if (true === method_exists($migrationInstance, 'postDown'))
        {
            $migrationInstance->postDown($conn);
        }

        $this->updateSchemaVersion($prev_version, $database);
    }

    /**
     * Get config database
     * @param unknown $databases
     * @throws Exception
     */
    protected function getDatabaseKeys()
    {
        $databases = $this->config->get('databases');
        if (!$databases)
        {
            throw new Exception("Database settings are not found.");
        }
        return array_keys($databases);
    }

    /**
     * Validate config database names.
     * @param unknown $databases
     * @throws Exception
     */
    protected function validateDatabaseNames($databases)
    {
        $configDatabaseNames = $this->getDatabaseKeys();
        foreach ($databases as $dbname)
        {
            if (false === array_search($dbname, $configDatabaseNames))
            {
                throw new Exception("Database '".$dbname."' is not defined.");
            }
        }
    }

    /**
     * Get defined database names
     * @throws Exception
     */
    protected function getDatabaseNames($databases = array())
    {
        if($databases)
        {
            $this->validateDatabaseNames($databases);
        }
        else
        {
            $databases = $this->getDatabaseKeys();
        }

        return $databases;
    }

    /**
     * Get PDO connection
     * @return PDO
     */
    protected function getConnection($database)
    {
        if (false === isset($this->conns[$database]))
        {
            $dsn      = $this->config->get('databases/'.$database.'/dsn');
            $user     = $this->config->get('databases/'.$database.'/user');
            $password = $this->config->get('databases/'.$database.'/password');

            $this->conns[$database] = new \PDO($dsn, $user, $password);
            $this->conns[$database]->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }

        return $this->conns[$database];
    }

    protected function getValidMigrationUpFileList($database, $version)
    {
        $valid_files = array();

        $files = $this->getMigrationFileList($database);
        foreach ($files as $file)
        {
            preg_match ("/^\d+/", basename($file), $matches);
            $timestamp = $matches[0];

            if ($timestamp > $version)
            {
                $valid_files[] = $file;
            }
        }

        return $valid_files;
    }

    protected function getValidMigrationDownFileList($database, $version)
    {
        $valid_files = array();

        $files = $this->getMigrationFileList($database);
        rsort($files);
        foreach ($files as $file)
        {
            preg_match ("/^\d+/", basename($file), $matches);
            $timestamp = $matches[0];

            if ($timestamp <= $version)
            {
                $valid_files[] = $file;
            }
        }

        return $valid_files;
    }

    protected function getMigrationFileList($database)
    {
        $files  = array();
        $classes = array();
        $gfiles  = glob($this->migration_dir.'/*');


        foreach ($gfiles as $file)
        {
            if (1 === preg_match("/^\d+_".$database."\..+\.php$/", basename($file)))
            {
                preg_match("/(\d+)_(.*)\.php$/", basename($file), $matches);
                $version    = $matches[1];
                $className = Utils::camelize($matches[2]);

                // Check to exist same class name.
                if (true === array_key_exists($className, $classes))
                {
                    // Can't use same class name to migration tasks.
                    throw new Exception("Can't use same class name to migration tasks. Duplicate migration task name [".$classes[$className]."] and [".$file."].");
                }

                $classes[$className] = $file;
                $files[] = $file;
            }
        }

        sort($files);
        return $files;
    }

    /**
     * Check migration file validation.
     */
    protected function checkAllMigrationFileList()
    {
        $files   = array();
        $classes = array();
        $gfiles  = glob($this->migration_dir.'/*');

        foreach ($gfiles as $file)
        {
            if (1 === preg_match("/^\d+_.+\.php$/", basename($file)))
            {

                preg_match("/(\d+)_(.*)\.php$/", basename($file), $matches);
                $version    = $matches[1];
                $className = Utils::camelize($matches[2]);

                $class_text = file_get_contents($file);

                if (1 !== preg_match("/class +$className/", $class_text))
                {
                    throw new Exception("Unmatch defined class in the $file. You must define '$className' class in that file.");
                }

                // Check to exist same class name.
                if (true === array_key_exists($className, $classes)
                    && $classes[$className] != $file)
                {
                    // Can't use same class name to migration tasks.
                    throw new Exception("Can't use same class name to migration tasks. Duplicate migration task name [".$classes[$className]."] and [".$file."].");
                }

                $classes[$className] = $file;
                $files[] = $file;
            }
        }
    }

    protected function updateSchemaVersion($version, $database)
    {
        if($version !== null)
        {
            $this->logger->write("Setting schema version '$version' from '$database'", null, "debug");
        }
        else
        {
            $this->logger->write("Setting schema version initialization from '$database'", null, "debug");
        }
        if (true === empty($version))
        {
            $version = null;
        }

        $fp_cpl=fopen($this->version_path.'/.'.$database, 'wb');
        if (false===$fp_cpl)
        {
            throw new Exception("Can't write version file ".$this->version_path.'/'.$database);
        }
        fwrite($fp_cpl, $version);
        fclose($fp_cpl);
    }

    protected function getSchemaVersion($database)
    {
        $this->logger->write("Getting schema version from '$database'", null, "debug");

        if(false === is_file($this->version_path.'/.'.$database))
        {
            $this->updateSchemaVersion('', $database);
        }
        $fp=fopen($this->version_path.'/.'.$database, 'rb');
        $is_version = filesize($this->version_path.'/.'.$database);
        if($is_version)
        {
            $version = fread($fp, $is_version);
            fclose($fp);
            $this->logger->write("Current schema version is ".$version, "[$database]");
        }
        else
        {
            $version = null;
            $this->logger->write("Current schema version empty ", "[$database]");
        }

        return $version;
    }

}
