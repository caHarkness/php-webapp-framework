<?php
    class Cookie
    {
        public static function get($strKey)
        {
            if (isset($_COOKIE))
                if (is_array($_COOKIE))
                    if (array_key_exists($strKey, $_COOKIE))
                        if ($_COOKIE[$strKey] !== null && strlen($_COOKIE[$strKey]) > 0)
                            return $_COOKIE[$strKey];

            return null;
        }

        public static function set($strKey, $strValue = null)
        {
            if ($strValue == null || strlen($strValue) < 1)
            {
                if (isset($_COOKIE[$strKey]))
                {
                    unset($_COOKIE[$strKey]);
                    setcookie(
                        $strKey,
                        "",
                        time() - 3600,
                        "/");
                }

                return null;
            }

            $_COOKIE[$strKey] = $strValue;

            setcookie(
                $strKey,
                $strValue,
                time() + 60 * 60 * 24 * 30,
                "/");

            return $strValue;
        }
    }
?>
