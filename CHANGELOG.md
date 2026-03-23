# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2026-03-23

- Added exception tracking via `kernel.exception` event listener
- ExceptionHandler captures class, message, file, line, source code context, and filtered stack trace
- Source code context encodes leading whitespace as HTML entities to survive middleware trimming
- Stack trace filters to app frames + first vendor frame for readability
- PayloadCollector stores first exception only (root cause preservation)
- Hash-based deduplication (md5 of class + file + line)

## [0.1.4] - 2026-03-19

- Removed `track_http_requests` config option — outgoing HTTP tracking now follows the global `enabled` setting

## [0.1.3] - 2026-03-19

- Removed dev artifacts from package (`.vscode/`, `public/fonts/`, `.env.example`, `temp_auto_push.bat`)
- Added `.vscode/` to `.gitignore`

## [0.1.2] - 2026-03-19

- Renamed env prefix from `APEX_TOOLBOX_` to `APEXTOOLBOX_` to match brand
- Replaced Laravel-specific default path filters (`telescope/*`, `horizon/*`, `_debugbar/*`) with Symfony equivalents (`_profiler/*`, `_wdt/*`)
- Expanded README with full configuration reference (headers, body, response filtering with exclude/mask)
- Added `track_http_requests` option to README
- Added link to [apextoolbox.com](https://apextoolbox.com/)

## [0.1.1] - 2026-03-19

- Fixed `stream()` signature for Symfony 5.4 http-client-contracts v2.x compatibility

## [0.1.0] - 2026-03-18

- Initial stable release
- HTTP request/response tracking via event listeners
- Outgoing HTTP request tracking via HttpClient decorator
- Log capture via Monolog handler with source introspection
- Exception tracking with stack traces and code context
- Doctrine query logging with server-side N+1 detection
- Sensitive data filtering (exclude and mask) for headers, body, and response
- Path filtering with include/exclude glob patterns
- UUID v7 trace IDs
- Async payload delivery
