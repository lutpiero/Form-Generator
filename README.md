# Form Generator

A **Form Generator** web application with a beautiful and elegant UI, built with **Laravel 13** (PHP) and **MySQL**. Features an admin dashboard to create and manage forms with various field types, spam protection, and submission management.

---

## ✨ Features

- 🛠️ Admin dashboard to create and manage forms
- 📋 Field types: text, phone number, email, dropdown, radio button, checkbox, textarea, and more
- 🔒 Spam protection with CAPTCHA (math question & honeypot) — enable/disable per form
- 🌐 Public-facing form pages for submissions
- 📊 View all submissions in the admin backend with a data table
- 📥 Export submissions as CSV
- 🔐 Authentication with Laravel Breeze (login, register, password reset)

---

## 🖥️ Requirements

| Requirement | Version |
|---|---|
| Ubuntu | 20.04 / 22.04 / 24.04 |
| PHP | 8.3+ |
| Composer | 2.x |
| Node.js | 18+ |
| npm | 9+ |
| MySQL | 8.0+ |
| Git | any |

---

## 🚀 Installation on Ubuntu

### 1. Update system packages

```bash
sudo apt update && sudo apt upgrade -y
```

### 2. Install PHP 8.3 and required extensions

```bash
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3 php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring \
    php8.3-xml php8.3-bcmath php8.3-curl php8.3-zip php8.3-tokenizer php8.3-sqlite3
```

### 3. Install Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### 4. Install Node.js and npm

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node --version && npm --version
```

### 5. Install and configure MySQL

```bash
sudo apt install -y mysql-server
sudo systemctl start mysql
sudo systemctl enable mysql

# Secure the installation (set root password when prompted)
sudo mysql_secure_installation
```

Create a database and user for the app:

```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE form_generator CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'formuser'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON form_generator.* TO 'formuser'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 6. Install Git and clone the repository

```bash
sudo apt install -y git
git clone https://github.com/lutpiero/Form-Generator.git
cd Form-Generator
```

### 7. Install PHP dependencies

```bash
composer install
```

### 8. Set up environment file

```bash
cp .env.example .env
```

Edit `.env` and update the database settings:

```bash
nano .env
```

Update the following values:

```dotenv
APP_NAME="Form Generator"
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=form_generator
DB_USERNAME=formuser
DB_PASSWORD=your_password
```

### 9. Generate application key

```bash
php artisan key:generate
```

### 10. Run database migrations and seeders

```bash
php artisan migrate --seed
```

> This will create all tables and seed a default admin user and demo form.

**Default admin credentials:**
| Field | Value |
|---|---|
| Email | `admin@example.com` |
| Password | `password` |

### 11. Install Node.js dependencies and build assets

```bash
npm install
npm run build
```

### 12. Set correct file permissions

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 13. Start the development server

```bash
php artisan serve
```

The application will be available at: **http://localhost:8000**

---

## 🔧 Running in Production (with Nginx)

### Install Nginx

```bash
sudo apt install -y nginx
```

### Configure Nginx virtual host

```bash
sudo nano /etc/nginx/sites-available/form-generator
```

Paste the following configuration (update `server_name` and `root` as needed):

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/Form-Generator/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable the site and restart Nginx:

```bash
sudo ln -s /etc/nginx/sites-available/form-generator /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

Set ownership to `www-data`:

```bash
sudo chown -R www-data:www-data /var/www/Form-Generator
```

---

## 🧪 Running Tests

```bash
php artisan test
```

---

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
