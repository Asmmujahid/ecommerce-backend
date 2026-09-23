# 🛒 E-Commerce Backend API

A scalable **RESTful API backend** for a full-stack e-commerce application, built with **Laravel 12, PHP, MySQL, Laravel Sanctum, and Spatie Permission**.

The API provides authentication, role-based access control, product management, inventory management, orders, carts, wishlists, coupons, reviews, addresses, seller management, and other core e-commerce functionality.

---

## 🚀 Live API

**Production API:**

https://ecommerce-backend-production-2736.up.railway.app/

> This URL points to the Laravel API backend. Individual endpoints may require specific HTTP methods and authentication, so opening an API endpoint directly in a browser may return an HTTP method or authentication message.

---

## 📌 Project Overview

This backend was developed as part of a full-stack e-commerce application and is designed to provide a structured API layer for a React frontend.

The backend demonstrates practical experience with:

* Laravel 12
* RESTful API development
* Laravel Sanctum authentication
* Role-based access control
* Spatie Permission
* MySQL database design
* Eloquent ORM
* Database relationships
* CRUD operations
* API validation
* Middleware
* Service-layer architecture
* Product and inventory management
* Order processing
* Git and GitHub
* Production deployment

The API supports three primary user roles:

* **Admin**
* **Seller**
* **Customer**

---

## ✨ Key Features

### 🔐 Authentication & Authorization

* User registration
* User login
* User logout
* Laravel Sanctum authentication
* Protected API routes
* Role-based access control
* Permission management
* Admin access control
* Seller access control
* Customer access control
* Seller approval system

### 👨‍💼 Admin Management

Administrators can manage the overall e-commerce platform.

Features include:

* Admin dashboard
* User management
* Seller management
* Seller approval
* Product management
* Category management
* Brand management
* Inventory management
* Order management
* Coupon management
* Reports
* Platform settings

### 🏪 Seller Management

Sellers can manage their products and related inventory.

Features include:

* Seller dashboard
* Product CRUD
* Product variants
* Product images
* Product pricing
* Product stock
* Inventory management
* Order management
* Seller profile

### 📦 Product Management

The API supports:

* Product creation
* Product updates
* Product deletion
* Product categories
* Product brands
* Product images
* Product variants
* Product pricing
* Product stock
* Product status

### 🔎 Product Search & Filtering

Product APIs support:

* Search
* Category filtering
* Brand filtering
* Price filtering
* Sorting
* Pagination

### 🛒 Cart Management

* Add products to cart
* Update cart quantities
* Remove cart items
* Calculate cart totals
* Variant-based cart items

### ❤️ Wishlist

* Add products to wishlist
* Remove products from wishlist
* Retrieve customer wishlist

### 📋 Order Management

* Create orders
* Store order items
* Manage order status
* Customer order history
* Seller order management
* Admin order management
* Order cancellation based on order status

### 📊 Inventory Management

* Product inventory
* Variant inventory
* Stock quantity tracking
* Inventory updates
* Admin inventory management

### 🎟️ Coupon Management

* Create coupons
* Update coupons
* Delete coupons
* Validate coupons
* Calculate discounts
* Apply coupons during checkout

### ⭐ Review Management

* Customer product reviews
* Product ratings
* Review management

### 📍 Address Management

* Customer addresses
* Shipping addresses
* Address management
* Address selection during checkout

---

## 🧰 Technology Stack

| Technology        | Purpose                        |
| ----------------- | ------------------------------ |
| Laravel 12        | Backend framework              |
| PHP               | Server-side programming        |
| MySQL             | Relational database            |
| Laravel Sanctum   | API authentication             |
| Spatie Permission | Roles and permissions          |
| Eloquent ORM      | Database interaction           |
| REST APIs         | Frontend/backend communication |
| Composer          | PHP dependency management      |

### Development Tools

* Git
* GitHub
* VS Code
* Postman
* XAMPP
* Composer

### Deployment

* Railway
* MySQL

---

## 🏗️ Backend Architecture

```text
React Frontend
      │
      │ HTTP / REST API
      ▼
┌─────────────────────────┐
│       Laravel 12        │
│                         │
│ Controllers             │
│ Services                │
│ Middleware              │
│ Form Requests           │
│ Models / Eloquent       │
│ Authentication          │
└────────────┬────────────┘
             │
             ▼
┌─────────────────────────┐
│         MySQL           │
│                         │
│ Users                   │
│ Products                │
│ Orders                  │
│ Cart                    │
│ Inventory               │
│ Reviews                 │
│ Coupons                 │
│ Addresses               │
└─────────────────────────┘
```

---

## 🗂️ Backend Project Structure

```text
ecommerce-backend/
│
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   └── Requests/
│   │
│   ├── Models/
│   └── Services/
│
├── bootstrap/
├── config/
├── database/
│   ├── migrations/
│   └── seeders/
├── public/
├── resources/
├── routes/
│   ├── api.php
│   ├── admin.php
│   ├── seller.php
│   └── customer.php
├── storage/
├── .env.example
├── artisan
├── composer.json
└── README.md
```

---

## 🔑 Authentication Flow

The application uses **Laravel Sanctum** for API authentication.

```text
User
 │
 ▼
Register / Login
 │
 ▼
Laravel Authentication
 │
 ▼
Sanctum Token
 │
 ▼
Authenticated API Request
 │
 ▼
Role & Permission Middleware
 │
 ├── Admin
 ├── Seller
 └── Customer
```

Protected API resources are accessible according to the authenticated user's role and permissions.

---

## 🗄️ Main Database Models

The backend contains models for the application's major e-commerce operations:

```text
User
Product
Category
Brand
Order
OrderItem
Cart
CartItem
Wishlist
Coupon
Address
Payment
Review
Banner
Notification
Vendor
ProductImage
ProductVariant
Inventory
ReturnRequest
```

These models use Laravel Eloquent relationships to represent the application's database structure and business logic.

---

## 🔌 API Modules

The backend provides API functionality for:

* Authentication
* Admin
* Sellers
* Customers
* Products
* Categories
* Brands
* Product images
* Product variants
* Cart
* Wishlist
* Orders
* Order items
* Inventory
* Coupons
* Addresses
* Reviews

The React frontend communicates with these APIs using Axios.

---

## 🧪 API Testing

The API was tested during development using **Postman**.

Testing included:

* Authentication endpoints
* CRUD operations
* Protected routes
* Role-based routes
* Product APIs
* Seller APIs
* Customer APIs
* Admin APIs
* Cart APIs
* Wishlist APIs
* Order APIs
* Inventory APIs
* Coupon APIs
* Review APIs

---

## ⚙️ Local Installation

### 1. Clone the repository

```bash
git clone https://github.com/Asmmujahid/ecommerce-backend.git
```

Navigate into the project:

```bash
cd ecommerce-backend
```

### 2. Install dependencies

```bash
composer install
```

### 3. Create the environment file

Windows:

```bash
copy .env.example .env
```

Linux/macOS:

```bash
cp .env.example .env
```

### 4. Generate the application key

```bash
php artisan key:generate
```

### 5. Configure MySQL

Update your `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ecommerce_db
DB_USERNAME=root
DB_PASSWORD=
```

Use your own local database credentials.

### 6. Run migrations

```bash
php artisan migrate
```

### 7. Run database seeders

```bash
php artisan db:seed
```

### 8. Start the Laravel development server

```bash
php artisan serve
```

The API will normally be available at:

```text
http://127.0.0.1:8000
```

---

## 🔒 Environment & Security

Sensitive configuration should never be committed to GitHub.

Do **not** commit:

```text
.env
vendor/
node_modules/
```

The `.env.example` file should contain only configuration examples and must not contain real passwords, API keys, database credentials, or other secrets.

---

## 🌐 Frontend Integration

This backend is designed to work with a separate React frontend.

The React application communicates with this Laravel API using **Axios**.

Example local API configuration:

```env
VITE_API_URL=http://127.0.0.1:8000/api
```

### Production Frontend

https://ecommerce-frontend-nine-blue.vercel.app/

---

## 🚀 Deployment

The Laravel backend has been deployed to **Railway**.

### Production Backend

https://ecommerce-backend-production-2736.up.railway.app/

The backend is configured to serve API requests consumed by the deployed React frontend.

---

## 🎯 Skills Demonstrated

This project demonstrates practical experience with:

* Laravel 12
* PHP
* REST API development
* Laravel Sanctum
* Spatie Permission
* MySQL
* Eloquent ORM
* Authentication
* Authorization
* Middleware
* API validation
* CRUD operations
* Database relationships
* Service-layer architecture
* Product management
* Inventory management
* Order management
* Git
* GitHub
* Postman
* API integration
* Deployment

---

## 👩‍💻 Developer

### Asma Mujahid

**Software Engineer | Full Stack Developer**

**Technologies:**

```text
Laravel • PHP • React • JavaScript • MySQL
REST APIs • Tailwind CSS • Material UI
Git • GitHub
```

This project was developed as a portfolio application to demonstrate full-stack development, REST API architecture, authentication, authorization, database design, and production deployment.

---

## 🔗 Related Project

### E-Commerce Frontend

Built separately with:

```text
React + Vite
Redux Toolkit
Material UI
Tailwind CSS
Axios
React Hook Form
```

**Live Application:**

https://ecommerce-frontend-nine-blue.vercel.app/

---

⭐ **Thank you for visiting this project!**
