# SlimRack

A lightweight, self-hosted VPS and dedicated server inventory management system built with the [Slim Framework](https://www.slimframework.com/).

## About

SlimRack is a recreation of [Carotu](https://github.com/seikan/carotu) using the Slim 4 PHP framework. It provides a clean, modern architecture with support for multiple database backends (SQLite and MySQL/MariaDB), making it suitable for both local development and shared hosting environments like cPanel.

## Features

- **Server Inventory Management** - Track VPS and dedicated servers with detailed specifications
- **Provider Management** - Organize servers by hosting provider with control panel links
- **Billing Tracking** - Monitor prices, payment cycles, and renewal dates
- **Multi-Currency Support** - Handle multiple currencies with exchange rate conversion
- **REST API** - Full API with key-based authentication for external integrations
- **Database Flexibility** - Choose between SQLite (simple) or MySQL/MariaDB (scalable)
- **Easy Installation** - Web-based setup wizard for quick deployment
- **Responsive Design** - Bootstrap 5 interface that works on desktop and mobile

## Requirements

- PHP 8.1 or higher
- PDO extension with SQLite and/or MySQL driver
- Apache with mod_rewrite or Nginx
- Composer

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/asimzeeshan/SlimRack.git
   cd SlimRack
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Copy the environment file:
   ```bash
   cp .env.example .env
   ```

4. Point your web server to the `public/` directory

5. Visit the application in your browser and follow the installation wizard

## Configuration

Configuration is managed via the `.env` file:

```env
# Database (sqlite or mysql)
DB_DRIVER=sqlite
DB_HOST=localhost
DB_DATABASE=storage/database/slimrack.sqlite
DB_USERNAME=
DB_PASSWORD=

# Application
APP_KEY=your-random-32-character-key
APP_DEBUG=false

# Authentication
AUTH_USERNAME=admin
AUTH_PASSWORD_HASH=

# API Keys (comma-separated)
API_KEYS=
```

## Tech Stack

- **Framework**: [Slim 4](https://www.slimframework.com/)
- **Templating**: Twig
- **Frontend**: Bootstrap 5, DataTables, jQuery
- **Database**: SQLite or MySQL/MariaDB via PDO

## Credits

This project is a recreation of [Carotu](https://github.com/seikan/carotu) by [seikan](https://github.com/seikan), rebuilt using the Slim Framework for improved architecture and database flexibility.

## Author

**Asim Zeeshan**
- LinkedIn: [linkedin.com/in/asimzeeshan](https://www.linkedin.com/in/asimzeeshan)
- Email: 
- GitHub: [github.com/asimzeeshan](https://github.com/asimzeeshan)

## License

MIT License - See [LICENSE](LICENSE) file for details.
