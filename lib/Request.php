<?php
    class Request
    {
        public static $strScriptPath;
        public static $strResourcePath;
        public static $varArgs;
        public static $varPathParts;

        public static function process()
        {
            // .htaccess method:
            $strParam = Request::getParam("path");

            // PHP 5.3.9:
            if ($strParam == null)
                $strParam = "";
                
            foreach (["REQUEST_URI", "PATH_INFO"] as $k)
                if (strlen($strParam) < 1)
                {
                    if (is_array($_SERVER))
                        if (array_key_exists($k, $_SERVER))
                            if (strlen($_SERVER[$k]) > 0)
                                $strParam = $_SERVER[$k];
                }
                
            $strParam = preg_replace("/\?.*$/", "", $strParam);

            // REQUEST_URI method:
            if (strlen($strParam) < 1)
            {
                if (is_array($_SERVER))
                    if (array_key_exists("PATH_INFO", $_SERVER))
                        if (strlen($_SERVER["PATH_INFO"]) > 0)
                            $strParam = $_SERVER["PATH_INFO"];
            }

            $strPath = $strParam;
            $strPath = preg_replace("/^\//", "", $strPath);
            $strPath = preg_replace("/\/$/", "", $strPath);

            // Support for index arguments:
            Request::$varPathParts = explode("/", $strPath);

            // /test/action/a/b/c

            $fncIsFile = function($strScriptPath)
            {
                $varSearchPaths = [];

                if (is_dir("plugins"))
                    foreach (scandir("plugins") as $strPluginName)
                    {
                        if ($strPluginName == ".")  continue;
                        if ($strPluginName == "..") continue;

                        $strNewPath = "plugins/{$strPluginName}";
                        if (is_dir($strNewPath))
                            $varSearchPaths[] = "{$strNewPath}";
                    }

                // Try the framework's directory last:
                $varSearchPaths[] = ".";

                foreach ($varSearchPaths as $strSearchPath)
                    if (is_file(Request::$strScriptPath = "{$strSearchPath}/{$strScriptPath}"))
                        return true;

                Request::$strScriptPath = null;
                return false;
            };

            while (true)
            {
                if (strlen($strPath) < 1)
                    if ($fncIsFile("pages/index.php")) break;

                if ($fncIsFile("pages/{$strPath}/index.php")) break;
                if ($fncIsFile("pages/{$strPath}.php")) break;

                if (preg_match("/\//", $strPath))
                    $strPath = preg_replace("/\/[^\/]{1,}$/", "", $strPath);
                else $strPath = "";
            }

            $strArgs = str_replace("/{$strPath}", "", $strParam);
            $varArgs = explode("/", $strArgs);

            array_shift($varArgs);

            Request::$varArgs = $varArgs;
            Request::$strResourcePath = $strPath;
        }

        public static function processExtra()
        {
            $strInput       = file_get_contents("php://input");
            $strContentType = Request::getHeader("Content-Type");

            switch ($strContentType)
            {
                // Allow posted JSON bodies to be merged with $_POST:
                case "application/json":
                    if ($strInput !== null && strlen($strInput) > 0)
                    {
                        $o = json_decode($strInput, true);

                        foreach ($o as $k => $v)
                            $_POST[$k] = $v;
                    }
                    break;
            }
        }

        public static function getScript()
        {
            return Request::$strScriptPath;
        }

        public static function getArgs()
        {
            return Request::$varArgs;
        }

        public static function getPathParts()
        {
            return Request::$varPathParts;
        }

        public static function getPath()
        {
            $strPath = "/";
            $strPath .= implode("/", Request::getPathParts());
            return $strPath;
        }

        // Safely returns a request argument by its index or null if it doesn't exist (without error)
        public static function getArg($intIndex)
        {
            if (count(Request::$varArgs) >= $intIndex + 1)
                    return Request::$varArgs[$intIndex];
            else    return null;
        }

        // Safely returns the value of a request parameter or null if not defined (without error)
        // e.g. 12345 from getParam("id") when resource is like /users/get?id=12345
        public static function getParam($strKey)
        {
            if (is_array($_GET))
                if (array_key_exists($strKey, $_GET))
                    if (strlen($_GET[$strKey]) > 0)
                        return $_GET[$strKey];

            return null;
        }

        public static function getParams()
        {
            if (is_array($_GET))
                return $_GET;

            return null;
        }

        public static function getHeader($strKey)
        {
            if (is_array($_SERVER))
            {
                $strHeaderKey = "HTTP_{$strKey}";
                $strHeaderKey = strtoupper($strHeaderKey);
                $strHeaderKey = preg_replace("/\-/", "_", $strHeaderKey);

                if (array_key_exists($strHeaderKey, $_SERVER))
                    if (strlen($_SERVER[$strHeaderKey]) > 0)
                        return $_SERVER[$strHeaderKey];
            }

            return null;
        }

        // Returns true if all of the arguments are in the POST request
        // To be used like:
        // if (Request::posts("param1", "param2")) {
        public static function posts()
        {
            if (is_array($_POST))
                if (func_num_args() > 0)
                {
                    foreach (func_get_args() as $strKey)
                        if (!array_key_exists($strKey, $_POST))
                            return false;

                    return true;
                }

            return false;
        }

        // Returns the value of the posted field
        public static function getPosted($strKey = null)
        {
            if (is_array($_POST))
            {
                if ($strKey == null)
                    return $_POST;

                if (array_key_exists($strKey, $_POST))
                    if (strlen($_POST[$strKey]) > 0)
                        return $_POST[$strKey];
            }
            
            return null;
        }
    }

    // Call this no matter what to understand the request:
    Request::process();
    Request::processExtra();
?>
