# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.1] - 2025-10-02

### Fixed
- Improved app code detection in stack traces to work reliably across all platforms (Linux, macOS, Windows)
- Fixed vendor directory detection by normalizing path separators for cross-platform compatibility

### Enhanced
- Better identification of application code vs vendor code in exception stack traces
- More accurate `in_app` field in stack trace frames helps users focus on their own code

### Testing
- Added comprehensive tests for vendor code detection
- Added tests for `in_app` field validation in stack traces
- Increased test coverage from 45 to 47 tests with 177 assertions

## [2.1.0] - 2025-09-20

### 🚀 Added

#### Exception Handling System
- **Complete Exception Capture**: Automatic exception logging with full stack traces and code context
- **Exception Grouping**: SHA-256 hash generation for intelligent error grouping and deduplication
- **Code Context Extraction**: Extract source code around exception lines (10 lines before, 5 after) for debugging
- **App vs Vendor Detection**: Distinguish application code from vendor code in stack traces
- **Security**: Relative file paths prevent exposing absolute server paths

#### PayloadCollector Architecture
- **Unified Data Collection**: New `PayloadCollector` replaces `LogBuffer` with comprehensive data management
- **Request/Response Tracking**: Complete HTTP capture with performance metrics and real IP detection
- **Exception Integration**: Automatic exception attachment to HTTP request logs
- **Unified Payload Structure**: Combined logs, requests, responses, and exceptions in single payload

#### Advanced Data Security
- **Data Masking**: Replace sensitive values with `*******` while preserving data structure
- **Recursive Filtering**: Deep filtering works on nested objects and arrays at any level
- **Case-Insensitive Matching**: Automatic detection regardless of field name casing
- **Exclude vs Mask**: Two-tier protection with configurable precedence rules
- **Enhanced Defaults**: Comprehensive protection for SSN, phone, email, addresses, etc.

#### Enhanced Configuration
- **Response Filtering**: New configuration section for response data filtering and masking
- **Extended Security**: More comprehensive default protection for sensitive fields
- **Masking Options**: Separate mask and exclude arrays for granular control

### 🔧 Enhanced
- **Event Integration**: Added `onKernelException` event handling for automatic exception capture
- **Performance**: Improved timing precision and 2-second HTTP timeouts
- **IP Detection**: Real client IP extraction from forwarded headers
- **JSON Handling**: Proper support for both array and primitive JSON responses

### 🛡️ Security
- **Information Leakage Prevention**: Stack traces exclude sensitive argument data
- **Enhanced Header Filtering**: Improved filtering of authorization headers and API keys
- **Context Sanitization**: Automatic sanitization of exception context data

### 🧪 Testing
- **45 Test Cases**: Complete coverage with 123 assertions
- **PayloadCollector Tests**: 20+ dedicated tests for core functionality
- **Exception Handler Tests**: Full coverage of exception capture
- **PHP 8.4 Compatibility**: Clean support with no deprecation warnings

### 📚 Documentation
- **Complete README Rewrite**: Comprehensive installation, configuration, and usage guide
- **Security Documentation**: Detailed security best practices and disclaimers
- **Configuration Examples**: Real-world examples for production use

### Removed
- `LogBuffer` class (replaced by `PayloadCollector`)
- `LogBufferTest` (replaced by `PayloadCollectorTest`)

### Dependencies
- **Added**: `symfony/uid` for UUID v7 generation

## [2.0.0] - 2025-01-08

### Changed
- **BREAKING**: Complete architecture simplification to match Laravel logger
- Simplified ApexToolboxLogHandler constructor (only requires $config)
- Simplified LoggerListener (only requires $parameterBag)
- Updated README to concise SDK-style documentation
- Reduced from 65 to 28 tests focusing on core functionality

### Removed
- **BREAKING**: ContextDetector class (no longer needed)
- **BREAKING**: LogProcessor class (no longer needed)  
- **BREAKING**: SourceClassExtractor class (no longer needed)
- Removed complex service dependencies from services.yaml
- Cleaned up unused tests and legacy code

### Added
- LogBuffer category support (DEFAULT_CATEGORY, HTTP_CATEGORY)
- Simplified test suite matching Laravel approach

### Fixed
- All tests now passing with simplified architecture
- Consistent batch logging behavior across both packages

## [1.1.0] - 2025-01-XX

### Added
- **Universal Log Capture**: New Monolog handler that captures logs from console commands and background jobs, not just HTTP requests
- **Context Detection**: Automatic detection of execution context (`http`, `console`, `queue`) for each log entry
- **Source Class Detection**: Automatic extraction and inclusion of originating class/service names in log entries
- **Asynchronous Sending**: Non-blocking log transmission for queue workers and enhanced performance
- **New Configuration Options**: 
  - `universal_logging.enabled` to enable/disable console and queue logging
  - `universal_logging.types` to specify which log types to capture
- **New Services**:
  - `ContextDetector` - Detects execution context
  - `SourceClassExtractor` - Extracts source class names
  - `ApexToolboxHandler` - Monolog handler for universal log capture

### Enhanced
- **LoggerListener**: Now includes `source_class` and `type` fields in HTTP request logs
- **Configuration**: Extended with universal logging options while maintaining backward compatibility
- **Performance**: Improved asynchronous handling to prevent blocking application execution

### Technical Details
- Queue worker logs are sent immediately to prevent memory accumulation
- Console command logs are buffered and sent on shutdown for better performance
- Source class detection uses optimized backtrace analysis with configurable fallbacks
- All new functionality is disabled by default to ensure backward compatibility

### Breaking Changes
- None - All existing functionality remains unchanged and fully backward compatible

### Dependencies
- No new dependencies added
- Maintains compatibility with existing PHP 8.1+ and Symfony 5.4+/6.x/7.x requirements

## [1.0.4] - Previous Release

### Fixed
- Minor bug fixes and improvements
- Enhanced test coverage

## [1.0.0] - Initial Release

### Added
- Event-driven HTTP request/response logging
- Configurable path filtering with include/exclude patterns
- Security-focused header and body field filtering
- Monolog integration for application logs
- Symfony configuration system integration
- Silent failure handling