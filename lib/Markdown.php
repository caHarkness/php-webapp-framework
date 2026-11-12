<?php
    /**
     * Global markdown-to-HTML converter.
     * Usage: Markdown::toHTML($strMd);
     */
    class Markdown
    {
        /**
         * Convert a Markdown string to pure HTML (tags only, no CSS/classes).
         * Returns the result as a string (no echo/print).
         */
        public static function toHTML(string $strMarkdown): string
        {
            // Normalize line endings
            $strMarkdown = str_replace(["\r\n", "\r"], "\n", $strMarkdown);

            // 1. Pull fenced code blocks out (protect them from all other processing)
            $arrCodeBlocks = [];
            $strMarkdown = preg_replace_callback(
                "/^```[^\\n]*\\n(.*?)^```[ \\t]*$/ms",
                function (array $m) use (&$arrCodeBlocks): string
                {
                    $strCode = htmlspecialchars($m[1], ENT_QUOTES, "UTF-8");
                    $arrCodeBlocks[] = "<pre><code>{$strCode}</code></pre>";
                    $intIndex = count($arrCodeBlocks) - 1;
                    return "\x01{$intIndex}\x01";
                },
                $strMarkdown
            );

            // 2. Escape everything else so raw HTML in the input is neutralised
            $strMarkdown = htmlspecialchars($strMarkdown, ENT_QUOTES, "UTF-8");

            // 3. Inline elements (order matters: code, images, links, bold, italic, strike)
            $strMarkdown = preg_replace("/`([^`]+)`/", "<code>\$1</code>", $strMarkdown);
            $strMarkdown = preg_replace("/!\[([^\\]]*)\\]\\(([^)\\s]+)\\)/", "<img src=\"\$2\" alt=\"\$1\">", $strMarkdown);
            $strMarkdown = preg_replace("!\\[([^\\]]+)\\]\\(([^)\\s]+)\\)!", "<a href=\"\$2\">\$1</a>", $strMarkdown);
            $strMarkdown = preg_replace("/\\*\\*([^*]+)\\*\\*/", "<strong>\$1</strong>", $strMarkdown);
            $strMarkdown = preg_replace("/__([^_]+)__/s", "<strong>\$1</strong>", $strMarkdown);
            $strMarkdown = preg_replace("/\\*([^*]+)\\*/", "<em>\$1</em>", $strMarkdown);
            $strMarkdown = preg_replace("/_([^_]+)_/s", "<em>\$1</em>", $strMarkdown);
            $strMarkdown = preg_replace("/~~([^~]+)~~/", "<del>\$1</del>", $strMarkdown);

            // 4. Block elements, line by line
            $arrLines  = preg_split("/\\n/", $strMarkdown);
            $arrHtml   = [];
            $arrBuffer = []; // lines accumulating into the current paragraph

            $fnFlush = function () use (&$arrBuffer, &$arrHtml): void
            {
                if ($arrBuffer !== [])
                {
                    $strParagraph = implode(" ", array_map("trim", $arrBuffer));
                    $arrHtml[] = "<p>{$strParagraph}</p>";
                    $arrBuffer = [];
                }
            };

            $i = 0;
            $n = count($arrLines);
            while ($i < $n)
            {
                $t = trim($arrLines[$i]);

                if ($t === "")
                {
                    $fnFlush();
                    $i++;
                    continue;
                }

                // Fenced code block placeholder
                if (preg_match("/^\\x01(\\d+)\\x01$/", $t, $m))
                {
                    $fnFlush();
                    $arrHtml[] = $arrCodeBlocks[(int) $m[1]];
                    $i++;
                    continue;
                }

                // ATX heading
                if (preg_match("/^(#{1,6})\\s+(.+)$/", $t, $m))
                {
                    $fnFlush();
                    $intLevel = strlen($m[1]);
                    $arrHtml[] = "<h{$intLevel}>{$m[2]}</h{$intLevel}>";
                    $i++;
                    continue;
                }

                // Horizontal rule
                if (preg_match("/^(?:-{3,}|\\*{3,}|_{3,})$/", $t))
                {
                    $fnFlush();
                    $arrHtml[] = "<hr>";
                    $i++;
                    continue;
                }

                // Blockquote (">" was already escaped to "&gt;")
                if (preg_match("/^&gt;\\s?(.*)$/", $t))
                {
                    $fnFlush();
                    $arrQuote = [];
                    while ($i < $n && preg_match("/^&gt;\\s?(.*)$/", trim($arrLines[$i]), $q))
                    {
                        if ($q[1] !== "")
                        {
                            $arrQuote[] = $q[1];
                        }
                        $i++;
                    }
                    $strQuote = implode("<br>", $arrQuote);
                    $arrHtml[] = "<blockquote>{$strQuote}</blockquote>";
                    continue;
                }

                // Unordered list
                if (preg_match("/^[-*+]\\s+(.+)$/", $t))
                {
                    $fnFlush();
                    $arrItems = [];
                    while ($i < $n && preg_match("/^[-*+]\\s+(.+)$/", trim($arrLines[$i]), $l))
                    {
                        $arrItems[] = "<li>{$l[1]}</li>";
                        $i++;
                    }
                    $strItems = implode("", $arrItems);
                    $arrHtml[] = "<ul>{$strItems}</ul>";
                    continue;
                }

                // Ordered list
                if (preg_match("/^\\d+\\.\\s+(.+)$/", $t))
                {
                    $fnFlush();
                    $arrItems = [];
                    while ($i < $n && preg_match("/^\\d+\\.\\s+(.+)$/", trim($arrLines[$i]), $l))
                    {
                        $arrItems[] = "<li>{$l[1]}</li>";
                        $i++;
                    }
                    $strItems = implode("", $arrItems);
                    $arrHtml[] = "<ol>{$strItems}</ol>";
                    continue;
                }

                // Regular text: accumulate into a paragraph
                $arrBuffer[] = $arrLines[$i];
                $i++;
            }
            $fnFlush();

            return implode("\n", $arrHtml);
        }
    }
?>
