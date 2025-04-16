# DevMarket Philippines

A digital marketplace platform for Filipino developers to sell their digital products, source code, UI templates, and systems.

## Features

- User-friendly marketplace for digital products
- Secure authentication system
- Clean product listings with categories and filtering
- Contact seller functionality with phone and messenger options
- Admin dashboard with product management and reports
- Responsive design for all devices

## Installation

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- XAMPP, WAMP, LAMP, or MAMP (for local development)

### Setup Instructions

1. Clone or download this repository to your web server's document root folder (e.g., `htdocs` for XAMPP)

2. Create a MySQL database named `devmarket_ph` (or update the database name in `config/database.php`)

3. Import the database schema:
   - You can use the installation script by navigating to `http://localhost/DevMarket/install.php`
   - Or manually import the SQL file from `database/devmarket_ph.sql` using phpMyAdmin or MySQL command line

4. Configure your database connection:
   - Open `config/database.php` and update the database credentials if needed

5. Set proper folder permissions:
   ```bash
   chmod 755 -R /path/to/DevMarket
   chmod 777 -R /path/to/DevMarket/uploads
   ```

6. Access the site:
   - Open your browser and navigate to `http://localhost/DevMarket`

## Default Admin Access

- Username: admin
- Password: admin123

## Project Structure

```
DevMarket/
├── admin/                  # Admin dashboard files
├── assets/
│   ├── css/                # Stylesheet files
│   ├── js/                 # JavaScript files
│   └── images/             # Image assets
├── config/                 # Configuration files
├── database/               # Database schema
├── includes/               # Reusable PHP components
│   ├── header.php
│   ├── footer.php
│   └── functions.php
├── uploads/                # User uploaded files
├── index.php               # Homepage
├── login.php               # User login
├── register.php            # User registration
├── products.php            # Products listing
├── install.php             # Installation script
└── README.md               # This file
```

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Credits

- Bootstrap 5 - Frontend framework
- Font Awesome - Icons
- PHP & MySQL - Backend 