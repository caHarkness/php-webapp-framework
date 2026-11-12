# PHP Code Style

## Strings

- Always use double quotes for string literals; never single quotes.
- Escape embedded double quotes as `\"` and regex backreferences as `\$1` / `\$2` so they are not interpolated as variables.
- Never concatenate with the `.` operator. Build one large double-quoted literal and inject values with interpolation (`{$var}`). Assign intermediate results to variables beforehand, e.g.:
  ```php
  $strName = htmlspecialchars($arrUser["name"], ENT_QUOTES, "UTF-8");
  return "<span class=\"name\">{$strName}</span>";
  ```

## Identifiers

- Descriptive camelCase names with a leading type prefix: `str` (string), `arr` (array), `int` (integer), `fn` (function/closure), e.g. `strMessage`, `arrResults`, `intCount`, `fnCallback`.
- Single-character names (`i`, `n`, `m`, `q`, `l`, `t`) are allowed for loop counters and regex match captures.

## Braces (Allman style)

- Every opening brace `{` sits on its own line; a conditional or a function/closure signature is never followed by an open bracket.
- Every closing brace `}` also sits on its own line.

## Control flow

- `if`/`while` conditions end with a line break; the body starts on the line after the opening brace.
- Prefer early `continue` over nested blocks to keep nesting shallow.

## Classes and methods

- Logic lives in static methods of a global class, called without instantiation, e.g. `Processor::handle($strInput)`.
- Declare parameter and return types, and return the result instead of `echo`/`print`.
- Document the purpose in a docblock comment above the declaration.
- Numbered step comments (`// 1. ...` `// 2. ...`) mark pipeline stages.

## File structure

- Every PHP file begins with `<?php` on its own line and closes with `?>` on its own line.
- All code inside is indented by one level (4 spaces) from the opening `<?php`, e.g.:
  ```php
  <?php
      class Example
      {
          public static function run()
          {
              // ...
          }
      }
  ?>
  ```

## General

- 4-space indentation, no tabs.
- Escape untrusted input before processing (`htmlspecialchars`).
