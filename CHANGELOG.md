# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.4] - 2026-01-23

### Added
- Permission system with Editor/Viewer roles
- Trash functionality with 30-day auto-cleanup
- Private contracts (only visible to creator)
- Read-only contract view for users with Viewer permission
- Nextcloud Initial State API for admin detection

### Changed
- Improved E-Mail reminder texts with personal greeting
- Viewer users can now view contract details (read-only)
- "New Contract" button hidden for Viewer users

### Fixed
- Mount point conflict between header height and admin detection
- Permission dropdown now loads all users/groups on open

## [0.1.3] - 2026-01-20

### Added
- Internationalization (i18n) with German and English translations
- Error handling improvements

### Fixed
- Date timezone bug in contract dates
- Access control and data isolation vulnerabilities
- Table name length issues for PostgreSQL compatibility

### Security
- Fixed data isolation between users

## [0.1.2] - 2026-01-19

### Added
- Admin and User settings UI
- Talk integration for reminders (via ChatManager API)
- E-Mail reminders with HTML and plain text
- Two reminder timepoints (configurable: default 14 and 3 days before deadline)

### Changed
- Use Nextcloud-native access control instead of custom middleware

### Removed
- Nextcloud Notification (bell) - Talk and E-Mail are sufficient

## [0.1.1] - 2026-01-18

### Added
- Archive functionality with restore option
- Validation with ValidationException and ForbiddenException
- Date utilities (dateUtils.js, periodUtils.js)

### Fixed
- PostgreSQL compatibility: Use PARAM_INT instead of PARAM_BOOL
- Shortened table names to avoid index length issues

## [0.1.0] - 2026-01-17

### Added
- Initial release
- Contract CRUD operations (create, read, update, delete)
- Category management with sidebar filter
- Contract list with status badges
- File picker integration for contract documents
- German date format (DD.MM.YYYY)
- Structured cancellation period input

[Unreleased]: https://github.com/cpcMomentum/contractmanager/compare/v0.1.4...HEAD
[0.1.4]: https://github.com/cpcMomentum/contractmanager/compare/v0.1.3...v0.1.4
[0.1.3]: https://github.com/cpcMomentum/contractmanager/compare/v0.1.2...v0.1.3
[0.1.2]: https://github.com/cpcMomentum/contractmanager/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/cpcMomentum/contractmanager/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/cpcMomentum/contractmanager/releases/tag/v0.1.0
