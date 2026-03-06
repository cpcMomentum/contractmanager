# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.2] - 2026-03-06

### Fixed
- Repackaged release tarball to fix "Extracted app has more than 1 folder" update error (#38)

## [0.2.1] - 2026-03-03

### Added
- Support for fixed-term contracts without cancellation period (#28)
- Dynamic reminders: cancellation deadline for auto_renewal, expiry date for fixed contracts (#27)
- Code review documentation (docs/20260303_code-review.md)

### Changed
- Settings link moved to navigation footer (#1)
- Improved privacy toggle UX (#30)
- Cancellation period field only shown for auto_renewal contracts
- Email and Talk reminder messages now differentiate by contract type

### Security
- Added userId null-check in ContractController (H1)
- Added try-catch for DateTime parsing in validation (H2)
- Added noopener/noreferrer to all window.open() calls (M4)
- Added htmlspecialchars for email URLs (N1)

### Removed
- Unused CSS class .form-row--thirds (N3)

## [0.2.0] - 2026-02-27

### Added
- Filterable contract list with vendor, status, and contract type filters (#22)
- Sortable contract list with persistent user preference (#21)
- Duplicate contract action (#18)
- Folder icon in contract list to open contract folder (#15)

### Changed
- Display name renamed to "Verträge" (#17)
- Filter and sort preferences persist per user across page reloads
- Updated screenshots for App Store listing

### Fixed
- Categories now sorted alphabetically (#8)
- Invalid JSON in l10n translation files (#16)

## [0.1.5] - 2026-02-23

### Fixed
- FilePicker not opening on certain hosting providers due to extremely long webpack chunk filenames
- Selected folder/file name not visible after FilePicker selection (only in tooltip)

### Changed
- Nextcloud 33 compatibility added (max-version raised to 33)
- Webpack chunk filenames shortened to hash-based naming

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

[Unreleased]: https://github.com/cpcMomentum/contractmanager/compare/v0.2.2...HEAD
[0.2.2]: https://github.com/cpcMomentum/contractmanager/compare/v0.2.1...v0.2.2
[0.2.1]: https://github.com/cpcMomentum/contractmanager/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/cpcMomentum/contractmanager/compare/v0.1.5...v0.2.0
[0.1.5]: https://github.com/cpcMomentum/contractmanager/compare/v0.1.4...v0.1.5
[0.1.4]: https://github.com/cpcMomentum/contractmanager/compare/v0.1.3...v0.1.4
[0.1.3]: https://github.com/cpcMomentum/contractmanager/compare/v0.1.2...v0.1.3
[0.1.2]: https://github.com/cpcMomentum/contractmanager/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/cpcMomentum/contractmanager/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/cpcMomentum/contractmanager/releases/tag/v0.1.0
