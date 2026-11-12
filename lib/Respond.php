<?php
    class Respond
    {
        public static function status($intCode, $strReason)
        {
            $intCode    = intval($intCode);
            $strReason  = trim($strReason);

            ob_clean();
            header("HTTP/1.0 {$intCode} {$strReason}");
            header("Content-Type: text/plain");

            echo "{$intCode} {$strReason}";

            ob_end_flush();
            exit;
        }

        public static function json($varInput)
        {
            ob_clean();
            header("Content-Type: application/json");

            echo trim(json_encode($varInput, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_IGNORE));

            ob_end_flush();
            exit;
        }

        public static function redirect($strLocation)
        {
            ob_clean();
            header("Location: {$strLocation}");

            ob_end_flush();
            exit;
        }
    }
?>
