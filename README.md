# TNMMpamba Client Management System

A modern web application for managing client information, built with Laravel 9 and Vue.js 3.

## Features

- Simple client management interface
- Streamlined client creation with essential fields:
  - Customer ID
  - Name
  - Phone Number (with validation)
- Modern, responsive UI with Tailwind CSS
- Clean and intuitive data tables
- Easy-to-use edit functionality
- Docker support for easy deployment

## Tech Stack

- **Backend:** Laravel 9.x
- **Frontend:** Vue.js 3.x with Inertia.js
- **CSS Framework:** Tailwind CSS
- **Development Environment:** Docker (recommended) or XAMPP
- **Database:** MySQL 5.7
- **Web Server:** Nginx

## Prerequisites

Choose either Docker or Traditional setup:

### Docker Setup (Recommended)
- Docker Desktop
- Docker Compose

### Traditional Setup
- PHP >= 8.0
- Composer
- Node.js & NPM
- XAMPP or similar local development environment
- Git

## Installation

### Option 1: Docker Installation (Recommended)

1. Clone the repository:
```bash
git clone [your-repository-url]
cd TNMMpamba
```

2. Copy the environment file:
```bash
cp .env.example .env
```

3. Configure your .env file for Docker:
```env
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=tnmmpanda
DB_USERNAME=tnmmpanda
DB_PASSWORD=password
```

4. Build and start Docker containers:
```bash
docker-compose up -d --build
```

5. Install dependencies and set up the application:
```bash
docker-compose exec app composer install
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed
docker-compose exec app chmod -R 777 storage bootstrap/cache
```

6. Access the application:
   - Main application: http://localhost:8000
   - Database: localhost:3307 (Username: tnmmpanda, Password: password)

### Option 2: Traditional Installation

1. Clone the repository:
```bash
git clone [your-repository-url]
cd TNMMpamba
```

2. Install PHP dependencies:
```bash
composer install
```

3. Install JavaScript dependencies:
```bash
npm install
```

4. Copy the environment file:
```bash
cp .env.example .env
```

5. Generate application key:
```bash
php artisan key:generate
```

6. Configure your database in the .env file:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tnmmpamba
DB_USERNAME=root
DB_PASSWORD=
```

7. Run migrations:
```bash
php artisan migrate
```

8. Build assets:
```bash
npm run dev
```

## Usage

1. Start your local development server:
```bash
php artisan serve
```

2. Access the application at `http://localhost:8000`

## Development

### Using Docker

1. Start the containers:
```bash
docker-compose up -d
```

2. View container status:
```bash
docker-compose ps
```

3. Access container shells:
```bash
# PHP container
docker-compose exec app bash

# MySQL container
docker-compose exec db bash
```

4. View logs:
```bash
docker-compose logs -f
```

5. Stop containers:
```bash
docker-compose down
```

### Using Traditional Setup

For hot-reloading during development:
```bash
npm run watch
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.
