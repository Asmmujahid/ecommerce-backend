# 🛒 E-Commerce Backend API

A multi-vendor E-Commerce REST API built with Laravel 12, MySQL, Laravel Sanctum, and Spatie Permission.

## 🚀 Live API

https://ecommerce-backend-production-2736.up.railway.app

## 🌐 Live Application

https://ecommerce-frontend-nine-blue.vercel.app

## 🛠️ Technologies

- Laravel 12
- PHP
- MySQL
- Laravel Sanctum
- Spatie Permission
- REST API
- Git & GitHub
- Railway

## 👥 User Roles

### Admin
- Manage users
- Manage sellers
- Manage products
- Manage categories
- Manage brands
- Manage orders
- Manage inventory
- Manage coupons
- Manage vendors
- Manage reports

### Seller
- Manage products
- Manage product variants
- Manage inventory
- Manage orders
- Manage seller information

### Customer
- Register/login
- Browse products
- Search and filter products
- Add products to cart
- Wishlist
- Place orders
- Manage addresses
- Review products
- Track orders

## 🔐 Authentication

Authentication is implemented using Laravel Sanctum.

API authentication uses Bearer tokens.

## 📦 Main API Modules

- Authentication
- Products
- Categories
- Brands
- Cart
- Wishlist
- Orders
- Payments
- Reviews
- Coupons
- Addresses
- Inventory
- Notifications
- Vendors
- Product Variants
- Returns

## 🗄️ Database

MySQL is used as the primary database.

## ⚙️ Local Installation

Clone the repository:

git clone https://github.com/Asmmujahid/ecommerce-backend.git

Move into the project:

cd ecommerce-backend

Install PHP dependencies:

composer install

Create environment file:

cp .env.example .env

Generate application key:

php artisan key:generate

Configure your MySQL database in `.env`.

Run migrations:

php artisan migrate

Create storage link:

php artisan storage:link

Start the Laravel development server:

php artisan serve

The API will be available at:

http://127.0.0.1:8000

## 🔒 Environment Variables

Do not commit `.env` to GitHub.

Production environment variables are configured separately on Railway.

## 👩‍💻 Developer

**Asma Mujahid**

Software Engineer  
Laravel | React | MySQL

GitHub:

https://github.com/Asmmujahid

## 📄 License

This project was developed for portfolio, learning, and demonstration purposes.
