<?php
    class DatabaseConnection
    {
        private $strConnection;
        private $pdo;

        public $strEngine;

        public function __construct($strConnection, $strUsername = null, $strPassword = null)
        {
            $this->strConnection = $strConnection;

            // SQL Server:  "sqlsrv:Server={$strHost};Database={$strDatabaseName}"
            // MySQL:       "mysql:host={$strHost};dbname={$strDatabaseName}"
            // SQLite:      "sqlite:{$strFileName}"
            // SQLite:      "sqlite::memory:"

            $this->pdo = new PDO($strConnection, $strUsername, $strPassword);
            $this->pdo->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION);

            // Fetch the engine being used:
            preg_match(
                "/^([a-z]{1,})\:/",
                $strConnection,
                $varMatches);
            
            $this->strEngine = $varMatches[1];
        }

        private static function flatten(...$args)
        {
            if (is_null($args))
                return array();

            if (count($args) > 0)
            {
                $varOutput = array();
                $varFirst = array_shift($args);

                if (is_array($varFirst))
                    foreach ($varFirst as $varItem)
                        $varOutput = array_merge($varOutput, self::flatten($varItem));
                else
                array_push($varOutput, $varFirst);

                if (count($args) > 0)
                    $varOutput = array_merge($varOutput, self::flatten($args));

                return $varOutput;
            }
            else
            return array();
        }

        public function query($input)
        {
            $varArgs = func_get_args();

            // Support for single argument with multiple queries:
            if (count($varArgs) == 1 && is_array($varArgs[0]))
            {
                $varQueries = $varArgs[0];
                $varOutputs = [];

                while (count($varQueries) > 0)
                    array_push($varOutputs, $this->query(array_shift($varQueries)));

                return $varOutputs;
            }

            $varArgs = self::flatten($varArgs);

            if (count($varArgs) < 1)
                throw new Exception("query takes at least one argument, the query!");

            $strQuery = array_shift($varArgs);

            // Support for plugins' queries in db directory:
            $varQueryPaths = [];

            if (preg_match("/\.sql$/", $strQuery))
            {
                if (is_dir("plugins"))
                    foreach (scandir("plugins") as $strPluginName)
                    {
                        if ($strPluginName == ".")  continue;
                        if ($strPluginName == "..") continue;

                        array_push($varQueryPaths, "plugins/{$strPluginName}/db/{$strQuery}");
                        array_push($varQueryPaths, "plugins/{$strPluginName}/db/{$this->strEngine}/{$strQuery}");
                    }

                array_push($varQueryPaths, "db/{$strQuery}");
                array_push($varQueryPaths, $strQuery);
            }

            while (count($varQueryPaths) > 0)
            {
                $strTryPath = array_shift($varQueryPaths);
                if (is_file($strTryPath))
                {
                    $strQuery = file_get_contents($strTryPath);
                    break;
                }
            }

            if (preg_match("/^sqlsrv/", $this->strEngine))
                $strQuery = "set nocount on; {$strQuery}";

            $varStatement = $this->pdo->prepare($strQuery);

            if (count($varArgs) > 0)
                    $varStatement->execute($varArgs);
            else    $varStatement->execute();

            $varTemp    = array();
            $varOutput  = array();

            // Engines that do not support multiple rowsets:
            if (preg_match("/^(sqlite)/", $this->strEngine))
            {
                while ($varRow = $varStatement->fetch())
                {
                    $varNewRow = array();

                    foreach ($varRow as $k => $v)
                        if (!is_numeric($k))
                            $varNewRow[$k] = $v;

                    $varOutput[] = $varNewRow;
                }

                return $varOutput;
            }

            do
            {
                try { $varTemp[] = $varStatement->fetchAll(); }
                catch (Exception $x) {}
            }
            while ($varStatement->nextRowset());

            foreach ($varTemp as $i => $varSet)
                foreach ($varSet as $j => $varRow)
                {
                    $varNewRow = array();

                    foreach ($varRow as $k => $v)
                        if (!is_numeric($k))
                            $varNewRow[$k] = $v;

                    $varOutput[] = $varNewRow;
                }

            return $varOutput;
        }
    }
?>
