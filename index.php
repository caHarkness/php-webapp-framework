<?php
    ini_set("display_errors", "off");

    // Logging must be defined early on:
    class Framework
    {
        public static function log()
        {
            $strText = implode(" ", func_get_args());
            error_log("INFO: {$strText}");
        }
    }

    // For compatibility with PHP 5.3.0:
    $varConstants = array(
        array("JSON_HEX_TAG",                   1),
        array("JSON_HEX_AMP",                   2),
        array("JSON_HEX_APOS",                  4),
        array("JSON_HEX_QUOT",                  8),
        array("JSON_FORCE_OBJECT",              16),
        array("JSON_NUMERIC_CHECK",             32),
        array("JSON_UNESCAPED_SLASHES",         64),
        array("JSON_PRETTY_PRINT",              128),
        array("JSON_UNESCAPED_UNICODE",         256),

        array("JSON_OBJECT_AS_ARRAY",           1),
        array("JSON_BIGINT_AS_STRING",          2),
        array("JSON_PARSE_JAVASCRIPT",          4),

        array("JSON_ERROR_NONE",                0),
        array("JSON_ERROR_DEPTH",               1),
        array("JSON_ERROR_STATE_MISMATCH",      2),
        array("JSON_ERROR_CTRL_CHAR",           3),
        array("JSON_ERROR_SYNTAX",              4), 
        array("JSON_ERROR_UTF8",                5),
        array("JSON_ERROR_RECURSION",           6),
        array("JSON_ERROR_INF_OR_NAN",          7),
        array("JSON_ERROR_UNSUPPORTED_TYPE",    8),

        array("JSON_INVALID_UTF8_IGNORE",       1048576)
    );

    foreach ($varConstants as $k => $v)
        if (!defined($k))
            define($k, $v);

    ob_start();
    ob_clean();
    header("Content-Type: text/html");

    $strResource = $_SERVER["REQUEST_URI"];
    if (strlen($strResource) > 0)
        $strResource = substr($strResource, 1);

    $strPluginsDirectory    = "plugins";
    $varPaths               = array(".");

    if (is_dir($strPluginsDirectory))
        foreach (scandir($strPluginsDirectory) as $strPluginName)
        {
            if ($strPluginName == ".")  continue;
            if ($strPluginName == "..") continue;

            $strPluginDirectory = "{$strPluginsDirectory}/{$strPluginName}";
            if (is_dir($strPluginDirectory))
                $varPaths[] = "{$strPluginDirectory}";
        }

    foreach ($varPaths as $strPath)
    {
        $strTargetFilePath = "{$strPath}/{$strResource}";

        if (is_file($strTargetFilePath))
        {
            $intValid = 0;
            $varMimeTypes = array(
                array("/\.css$/", "text/css"),
                array("/\.js$/", "application/javascript"),
                array("/\.png$/", "image/png"),
                array("/\.jpg$/", "image/jpeg"),
                array("/\.jpeg$/", "image/jpeg"),
            );

            foreach ($varMimeTypes as $varMimeType)
                if (preg_match($varMimeType[0], $strTargetFilePath))
                {
                    header("Content-Type: {$varMimeType[1]}");
                    $intValid = 1;
                }

            if ($intValid)
            {
                Framework::log("Sending file {$strTargetFilePath}");
                ob_clean();
                echo file_get_contents($strTargetFilePath);
                ob_end_flush();
                exit;
            }
        }
    }

    $varCollection = array(
        "lib"           => array(),
        "init.php"      => array(),
        "head.php"      => array(),
        "header.php"    => array(),
        "footer.php"    => array(),
    );

    foreach ($varPaths as $strPath)
    {
        $x = null;

        if (is_dir($x = "{$strPath}/lib"))
            $varCollection["lib"][] = $x;

        foreach (["init.php", "head.php", "header.php", "footer.php"] as $strScript)
            if (is_file($x = "{$strPath}/{$strScript}"))
                $varCollection[$strScript][] = $x;
    }

    foreach ($varCollection["lib"] as $strLibraryDirPath)
        foreach (scandir($strLibraryDirPath) as $strLibraryFileName)
        {
            $strLibraryFilePath = "{$strLibraryDirPath}/{$strLibraryFileName}";
            if (preg_match("/\.php$/", $strLibraryFilePath))
                if (is_file($strLibraryFilePath))
                {
                    Framework::log("Requiring library {$strLibraryFilePath}");
                    require $strLibraryFilePath;
                }
        }

    $strBodyTagAttributes = "";

    function requireAll($strScriptName)
    {
        global $varCollection;
        foreach ($varCollection[$strScriptName] as $strScript)
        {
            if (is_file($strScript))
            {
                Framework::log("Requiring {$strScript}");
                require $strScript;
            }
        }
    }

    // Require all init scripts found:
    requireAll("init.php");
?>

<!DOCTYPE html>

<html>
    <head>
        <?php requireAll("head.php"); ?>
    </head>

    <body data-path="<?= Request::getPath(); ?>">
        <div class="app">
            <div class="app-header">
                <?php requireAll("header.php"); ?>
            </div>

            <div class="app-body">
                <?php
                    try
                    {
                        require Request::getScript();
                    }
                    catch (Exception $x)
                    {
                        ob_clean();
                        header("Content-Type: text/plain");
                        
                        $strMessage = $x->getMessage();
                        echo $strMessage;
                        echo "\n\n";

                        $strFile = $x->getFile();
                        $intLine = $x->getLine();
                        
                        echo "#-1 {$strFile}({$intLine}): {$strMessage}\n";
                        echo $x->getTraceAsString();

                        ob_end_flush();
                        exit;
                    }
                ?>
            </div>

            <div class="app-footer">
                <?php requireAll("footer.php"); ?>
            </div>
        </div>
    </body>
</html>

<?php
    ob_end_flush();
    exit;
?>
