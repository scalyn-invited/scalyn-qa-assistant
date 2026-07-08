# Scalyn QA Assistant

Website QA, SEO validation, and launch readiness tool for WordPress.

Built for internal use by [Scalyn](https://scalyn.global/).

## Documentation

See [IMPLEMENTATION.md](IMPLEMENTATION.md) for full architecture, feature list, and developer documentation.

## Requirements

- WordPress 6.0+
- PHP 8.2+

## Installation

### Option 1: Upload as ZIP (Recommended)

1. Download or clone the repository:
   ```bash
   git clone https://github.com/scalyn-invited/scalyn-qa-assistant.git
   cd scalyn-qa-assistant
   composer install --no-dev --optimize-autoloader
   ```
2. Zip the `scalyn-qa-assistant` folder.
3. In WordPress admin, go to **Plugins → Add New → Upload Plugin**.
4. Choose the zip file and click **Install Now**.
5. Click **Activate Plugin**.

### Option 2: Manual Install

1. Download or clone the repository:
   ```bash
   git clone https://github.com/scalyn-invited/scalyn-qa-assistant.git
   cd scalyn-qa-assistant
   composer install --no-dev --optimize-autoloader
   ```
2. Copy the `scalyn-qa-assistant` folder into `wp-content/plugins/`.
3. In WordPress admin, go to **Plugins** and activate **Scalyn QA Assistant**.

## License

GPL-2.0+
