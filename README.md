# Kek-Checkout

Live-Visitors-Counter and Point of Sale (POS) system for events with history, analysis, and offline synchronization.

## Features

- **POS System:** Fast, touch-optimized interface for recording sales, vouchers, and free drinks.
- **Offline-First:** Robust offline queue and synchronization logic using `localStorage`. Bookings are saved locally when offline and automatically synced when back online.
- **Admin Interface:** Manage event names, access tokens, settings, and view request logs.
- **Analysis & Reporting:** Detailed analysis of event data, including peaks, average stay duration, and retention.
- **Multi-language Support:** Fully internationalized (i18n) with German and English support.
- **Real-time Counter:** Live visitor counter with visual alerts for capacity thresholds.

## Installation

1. Clone the repository.
2. Ensure you have PHP 8.0 or higher installed.
3. Install dependencies using Composer:
   ```bash
   composer install
   ```
4. Configure your web server to point to the project root.
5. Ensure the `private/` and `archives/` directories are writable by the web server.

## Project Structure

- `index.php`: Main POS and visitor counter interface.
- `admin.php`: Administrative dashboard.
- `analysis.php`: Data analysis and reporting.
- `tablet.php`: Simplified interface for tablets.
- `private/`: Sensitive data and backend logic.
  - `src/`: PSR-4 autoloaded PHP classes.
  - `menu_items.json`: Product definitions.
  - `bookings.csv`: Live booking log.
- `assets/`: Frontend assets (CSS, JS, images, i18n).
- `archives/`: Archived event data (CSV files).

## Security

- Access to POS, Admin, and Analysis is protected by tokens.
- Tokens are stored locally in the browser and verified against `private/access_tokens.json` or `.admin_token`.
- CSRF protection is implemented for state-changing actions.

## License

MIT License. See `composer.json` for author details.
