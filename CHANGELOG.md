# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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