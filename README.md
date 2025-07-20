# Daily Diary - Product Management System

A comprehensive dairy product management system with beautiful UI and full CRUD operations.

## Features

### 🎨 Beautiful & Modern UI
- Responsive design with card-based layouts
- Smooth animations and hover effects
- Professional color scheme and typography
- Font Awesome icons for better UX
- Modal dialogs for forms

### 📦 Product Management
- **Complete CRUD Operations**: Create, Read, Update, Delete products
- **Product Fields**:
  - Product Name (unique)
  - Category (dropdown with categories table)
  - SKU/Product Code (unique)
  - Description (SEO-friendly)
  - Image upload (multiple formats supported)
  - Price (₹ per unit)
  - Discount (% or flat)
  - Final Price (auto-calculated)
  - Quantity Unit (dropdown)
  - Stock Quantity
  - Status (Active/Inactive)

### 🏷️ Category Management
- **Complete CRUD Operations** for categories
- **Smart Deletion**: Prevents deletion of categories with products
- **Product Count**: Shows number of products in each category
- **Status Management**: Active/Inactive categories

### 🔍 Advanced Features
- **Real-time Search**: Filter products by name
- **Category Filtering**: Filter by category
- **Status Filtering**: Filter by active/inactive status
- **Auto SKU Generation**: Based on product name
- **Price Calculation**: Automatic final price calculation
- **Image Upload**: Support for JPG, PNG, GIF, WebP formats
- **AJAX Integration**: Dynamic data loading for edit forms

### 🛡️ Security & Validation
- **Session Management**: Secure admin authentication
- **Input Validation**: Server-side validation
- **SQL Injection Protection**: Prepared statements
- **File Upload Security**: Type and size validation
- **XSS Protection**: HTML escaping

## Database Schema

### Categories Table
```sql
CREATE TABLE categories (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Products Table
```sql
CREATE TABLE products (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category_id INT(6) UNSIGNED,
    sku VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    image VARCHAR(255),
    price DECIMAL(10,2) NOT NULL,
    discount_percent DECIMAL(5,2) DEFAULT 0,
    final_price DECIMAL(10,2) GENERATED ALWAYS AS (price - (price * discount_percent / 100)) STORED,
    quantity_unit VARCHAR(50) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);
```

## Installation & Setup

1. **Database Configuration**
   - Update `config.php` with your database credentials
   - The system will automatically create required tables

2. **Admin Access**
   - Default admin credentials:
     - Email: `admin@gmail.com`
     - Password: `admin123`

3. **Sample Data**
   - Visit `/admin/sample_data.php` to populate sample categories and products
   - This will add 5 categories and 8 sample products

## File Structure

```
daily-diary/
├── admin/
│   ├── products.php          # Main product management
│   ├── categories.php        # Category management
│   ├── get_product.php       # API for fetching product data
│   ├── sample_data.php       # Sample data population
│   ├── style.css            # Admin panel styles
│   └── ...                  # Other admin files
├── images/
│   └── products/            # Product image uploads
├── config.php               # Database configuration
├── db_connect.php           # Database connection
└── README.md               # This file
```

## Usage

### Adding Products
1. Navigate to Admin Panel → Manage Products
2. Click "Add New Product"
3. Fill in product details
4. Upload product image (optional)
5. Click "Save Product"

### Managing Categories
1. Navigate to Admin Panel → Categories
2. Add, edit, or delete categories
3. Categories with products cannot be deleted

### Features in Action
- **Search**: Type in the search box to filter products
- **Filtering**: Use category and status dropdowns
- **Edit**: Click edit button to modify product details
- **Delete**: Click delete button (with confirmation)
- **Image Preview**: Product images display in cards

## Technical Details

### Frontend Technologies
- **HTML5**: Semantic markup
- **CSS3**: Modern styling with Flexbox and Grid
- **JavaScript**: Dynamic interactions and AJAX
- **Font Awesome**: Professional icons

### Backend Technologies
- **PHP**: Server-side logic
- **MySQL**: Database management
- **PDO**: Secure database operations
- **Session Management**: User authentication

### Key Features
- **Responsive Design**: Works on all devices
- **Progressive Enhancement**: Core functionality without JavaScript
- **Accessibility**: Proper ARIA labels and semantic HTML
- **Performance**: Optimized queries and efficient code

## Security Considerations

- All user inputs are validated and sanitized
- SQL injection protection via prepared statements
- XSS protection through HTML escaping
- File upload restrictions and validation
- Session-based authentication
- CSRF protection through form tokens

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is open source and available under the MIT License.

---

**Note**: This system is designed for dairy product management but can be easily adapted for other product types by modifying the categories and product fields.
