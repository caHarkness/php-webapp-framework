# php-webapp-framework

A barebones PHP web application framework for developing organized PHP code, websites, and microservices.

## Project Structure

```
index.php               Front controller (entry point for all requests)
app.sh                  Dev server launcher (PHP built-in server)
.htaccess               Apache rewrite rules (production)
pages/                  Routed page scripts
  index.php             Default page (serves 204 No Content)
lib/                    Framework libraries (auto-loaded)
  Request.php            Request routing & input access
  Respond.php            Response helpers (status, JSON, redirect)
  Cookie.php             Cookie get/set helpers
  DatabaseConnection.php PDO wrapper with .sql file support
  Markdown.php            Markdown-to-HTML converter
plugins/                (optional) Plugin directories for extensibility
```

## How the Framework Works

The framework is a **front controller** pattern. Every HTTP request is routed through a single entry point (`index.php`), which bootstraps the application, resolves the target script, and renders the response. There is no framework binary, no composer, and no build step — it is a set of PHP files that work together.

### Bootstrapping (`index.php`)

When a request arrives, `index.php` performs the following in order:

1. **Suppress errors** — `display_errors` is turned off.
2. **Define `Framework` class** — provides a `Framework::log()` method that writes to the PHP error log.
3. **Polyfill JSON constants** — for PHP 5.3.0 compatibility, any missing `JSON_*` constants are defined manually.
4. **Start output buffering** — `ob_start()` / `ob_clean()` so the response can be modified or discarded at any point.
5. **Set default `Content-Type: text/html`**.
6. **Extract the resource path** — reads `$_SERVER["REQUEST_URI"]` and strips the leading `/`.
7. **Build search paths** — starts with `"."` (the project root) and appends every subdirectory under `plugins/` if it exists. This allows plugins to contribute their own pages, libraries, and static assets.
8. **Serve static files** — for each search path, checks if `{$path}/{$resource}` is a file with a recognized MIME type (`.css`, `.js`, `.png`, `.jpg`, `.jpeg`). If matched, sets the correct `Content-Type`, outputs the file contents, and **exits immediately**. No further processing occurs.
9. **Collect libraries and scripts** — scans all search paths for:
   - `lib/` directories (all `.php` files inside are required)
   - `init.php`, `head.php`, `header.php`, `footer.php` files
10. **Require all libraries** — every `.php` file in every discovered `lib/` directory is `require`d. This loads the `Request`, `Respond`, `Cookie`, `DatabaseConnection`, and `Markdown` classes (and any plugin libraries).
11. **Require all `init.php` scripts** — any `init.php` files found across search paths are required. This is the hook for application-level setup (e.g., database connection, configuration).
12. **Render the HTML shell** — the framework outputs a full HTML document with four injection points:
    - `<head>` ← all `head.php` files
    - `.app-header` ← all `header.php` files
    - `.app-body` ← **the routed page script** (see below)
    - `.app-footer` ← all `footer.php` files
13. **Catch exceptions** — if the page script throws, the output buffer is cleaned, the exception message, file, line, and trace are printed as plain text, and the process exits.
14. **Flush and exit** — `ob_end_flush()` sends the buffered output to the client.

### Routing (`lib/Request.php`)

The `Request` class is loaded when `lib/` is required. At the bottom of the file, two methods are called automatically:

```php
Request::process();
Request::processExtra();
```

#### `Request::process()` — URL to Script Resolution

This method maps the incoming URL to a concrete PHP file on disk.

1. **Extract the path** — tries, in order:
   - `?path=` query parameter (set by `.htaccess` in Apache)
   - `$_SERVER["REQUEST_URI"]`
   - `$_SERVER["PATH_INFO"]`

   The query string is stripped, and leading/trailing slashes are removed.

2. **Split into path parts** — the path is exploded on `/` and stored in `Request::$varPathParts`.

3. **Walk up the path tree** — starting from the full path, the method checks for a matching script file at each level:
   - `pages/index.php` (for the root `/`)
   - `pages/{path}/index.php`
   - `pages/{path}.php`

   If no match is found, the last path segment is stripped and the check repeats, walking up toward the root. The first match wins. Search includes `plugins/*/` directories before falling back to the project root.

4. **Extract args** — the remaining path segments after the matched script are stored in `Request::$varArgs`. For example, if the URL is `/test/action/a/b/c` and the matched script is `pages/test.php`, then `Request::$varArgs` is `["action", "a", "b", "c"]`.

5. **Store results** — `Request::$strScriptPath` is set to the resolved file path, and `Request::$strResourcePath` to the matched path prefix.

#### `Request::processExtra()` — Body Parsing

Reads the raw request body from `php://input`. If the `Content-Type` is `application/json`, the JSON body is decoded and merged into `$_POST`, making JSON payloads accessible through the same `Request::getPosted()` API as form data.

#### Accessors

| Method | Returns |
|---|---|
| `Request::getScript()` | The resolved page script path |
| `Request::getArgs()` | All path args as an array |
| `Request::getArg($i)` | A single path arg by index, or `null` |
| `Request::getPath()` | The full path as a string (e.g. `/test/action`) |
| `Request::getPathParts()` | Path segments as an array |
| `Request::getParam($key)` | A `$_GET` value, or `null` |
| `Request::getParams()` | The full `$_GET` array |
| `Request::getHeader($key)` | A request header value, or `null` |
| `Request::posts(...$keys)` | `true` if all named keys exist in `$_POST` |
| `Request::getPosted($key)` | A `$_POST` value, or `null` |

### Responding (`lib/Respond.php`)

Three terminal helpers, each of which cleans the output buffer, sends headers, writes the body, flushes, and **exits**:

| Method | Behavior |
|---|---|
| `Respond::status($code, $reason)` | Sends `HTTP/1.0 {code} {reason}` with `Content-Type: text/plain` |
| `Respond::json($data)` | Sends a pretty-printed JSON body with `Content-Type: application/json` |
| `Respond::redirect($url)` | Sends a `Location` header (302) |

### Supporting Libraries

- **`Cookie`** — `Cookie::get($key)` reads a cookie; `Cookie::set($key, $value)` sets it (30-day expiry) or deletes it when the value is empty.
- **`DatabaseConnection`** — a thin PDO wrapper. The constructor takes a DSN string (MySQL, SQLite, SQL Server). `query()` accepts inline SQL or a `.sql` filename resolved from `db/` or `plugins/*/db/` directories. Supports bound parameters, multiple rowsets, and batch query arrays.
- **`Markdown`** — `Markdown::toHTML($str)` converts a Markdown string to pure HTML (no CSS/classes). Handles fenced code blocks, headings, lists, blockquotes, bold, italic, strikethrough, inline code, links, and images.

## Flow of a GET Request

Below is the complete, step-by-step path a `GET /` request takes through the framework.

### 1. Request arrives

```
GET / HTTP/1.1
Host: example.com
```

The server delivers this to `index.php` via one of two mechanisms:

- **Development** (`app.sh`): the PHP built-in server is started with `index.php` as the router script, so every request is handled by it.
- **Production** (Apache): the `.htaccess` rewrite rule catches all requests that do not map to an existing file or directory and internally rewrites them to `index.php?path=/$1`.

### 2. Front controller bootstraps (`index.php`)

```
index.php
  [1] ini_set("display_errors", "off")
  [2] class Framework { log() }
  [3] define JSON_* constants (PHP 5.3 compat)
  [4] ob_start(); ob_clean();
  [5] header("Content-Type: text/html")
  [6] $strResource = ""          // from REQUEST_URI "/" stripped
  [7] $varPaths = ["."]          // + plugins/*/ if present
  [8] Static file check:
         for each path in $varPaths:
             if is_file("{$path}/{$strResource}") and MIME matches
                 -> set Content-Type, echo file, exit
         (no match for "/")
  [9] Collect libraries & scripts:
         $varCollection["lib"]       = ["lib"]
         $varCollection["init.php"]  = []
         $varCollection["head.php"]  = []
         $varCollection["header.php"]= []
         $varCollection["footer.php"]= []
  [10] Require all lib/*.php:
         require lib/Cookie.php
         require lib/DatabaseConnection.php
         require lib/Markdown.php
         require lib/Request.php      <- triggers Request::process()
         require lib/Respond.php
  [11] requireAll("init.php")         // none found
  [12] Render HTML shell:
         <head>       <- requireAll("head.php")
         <body data-path="/">
           .app-header  <- requireAll("header.php")
           .app-body    <- require Request::getScript()  // "pages/index.php"
           .app-footer  <- requireAll("footer.php")
         </body>
```

### 3. Routing resolves (`Request::process()`, called during `require lib/Request.php`)

```
Request::process()
  [a] $strParam = ""              // no ?path=, REQUEST_URI is "/"
  [b] $strPath  = ""              // after stripping slashes
  [c] $varPathParts = [""]
  [d] while(true):
         strlen("") < 1 -> check pages/index.php -> EXISTS -> break
  [e] Request::$strScriptPath = "pages/index.php"
  [f] $strArgs = ""
  [g] $varArgs = []
  [h] Request::$varArgs = []
```

### 4. Page script executes

```
require "pages/index.php"
  -> Respond::status(204, "No Content")
       -> ob_clean()
       -> header("HTTP/1.0 204 No Content")
       -> header("Content-Type: text/plain")
       -> echo "204 No Content"
       -> ob_end_flush()
       -> exit
```

### 5. Response sent

```
HTTP/1.0 204 No Content
Content-Type: text/plain

204 No Content
```

Because `Respond::status()` calls `exit`, the HTML shell that was being built in `index.php` is discarded (the output buffer was cleaned). The client receives only the status response.

### 6. A routed GET request (e.g. `GET /users/42`)

```
GET /users/42 HTTP/1.1
```

```
index.php
  [6] $strResource = "users/42"
  [8] Static file check -> no match
  [10] Require lib/ -> Request::process() runs:
         $strPath = "users/42"
         -> pages/users/42/index.php?  no
         -> pages/users/42.php?        no
         -> strip -> "users"
         -> pages/users/index.php?     yes  <- match
         Request::$strScriptPath = "pages/users/index.php"
         Request::$varArgs = ["42"]
  [12] require "pages/users/index.php"
         -> $strId = Request::getArg(0);   // "42"
         -> $arrUser = $db->query("SELECT * FROM users WHERE id = ?", $strId);
         -> Respond::json($arrUser);
```

The page script has full access to `Request` (for args, params, headers, POST data) and `Respond` (for status, JSON, redirect), and can use any library class that was loaded from `lib/`.

## Extending with Plugins

Create a directory under `plugins/` to contribute additional code without modifying the core:

```
plugins/
  myplugin/
    lib/            Additional libraries (auto-required)
    init.php         Setup script (auto-required)
    head.php         Injected into <head>
    header.php       Injected into .app-header
    footer.php       Injected into .app-footer
    pages/           Additional routed scripts
    db/              .sql query files
```

All of these are discovered automatically by the front controller and the `Request` router. Plugin paths are searched **before** the project root, so a plugin can override a core page.

## Running

```bash
# Development (PHP built-in server, port 8000 by default)
./app.sh              # -> http://localhost:8000
./app.sh 3000          # -> http://localhost:3000
./app.sh --quiet       # suppress non-INFO log output

# Production (Apache with mod_rewrite)
# Point DocumentRoot at the project directory; .htaccess handles routing.
```

