# Changelog

All notable changes to this project will be documented in this file.

## [2.2.0] - 2024-08-24

### Added
- **New Services:**
  - `Transfers` service for bank and mobile money transfers
  - `Cards` service for card charging, tokenization, and preauthorization
  - `Subaccounts` service for managing subaccounts and payout subaccounts
  - `Plans` service for payment plans and subscription management
  - `MobileMoney` service for consolidated mobile money operations across African countries

- **Console Commands:**
  - `flutterwave:payment` - Generate payment links via CLI
  - `flutterwave:verify-webhook` - Verify webhook signatures via CLI
  - `flutterwave:refund` - Process transaction refunds via CLI

- **Data Transfer Objects (DTOs):**
  - `Customer` DTO for structured customer data
  - `PaymentRequest` DTO for type-safe payment requests

- **Utility Classes:**
  - `Utils` helper class with common utility functions
  - Currency formatting and conversion methods
  - Phone number validation and formatting for mobile money
  - Sensitive data masking for security

- **Enhanced Features:**
  - Quick payment creation method with `createPayment()`
  - Helper methods for easy service access
  - Comprehensive mobile money support for African countries
  - Bank account validation
  - Card tokenization and preauthorization
  - Subscription and payment plan management

### Changed
- Updated package version to 2.2.0
- Enhanced service registration in configuration
- Improved documentation with comprehensive examples
- Better error handling and logging

### Security
- Added automatic masking of sensitive data in logs
- Secure handling of card information
- Phone number validation and formatting

### Documentation
- Complete usage examples for all new services
- Console command documentation
- Mobile money integration examples
- Comprehensive README updates
- Updated TODO list with completed items

## [2.1.1] - Previous Version
- Basic transactions, webhooks, and modal services
- Initial Laravel integration