# Enhanced Blood Inventory Management System

## Overview
A comprehensive, user-friendly blood inventory management system designed for the Philippine Red Cross Baguio Chapter. This system provides real-time tracking, automated alerts, and role-based access control for blood unit management.

## Features

### 🎯 Core Functionality
- **Real-time Dashboard**: Live inventory summary with color-coded alerts
- **Advanced Filtering**: Filter by blood type, status, date range, and search
- **Unit Management**: Add, update, and track blood units with full audit trail
- **Automated Alerts**: Low stock warnings and expiring unit notifications
- **Role-based Access**: Different permission levels for different staff roles
- **Export Capabilities**: CSV export for reporting and analysis

### 📊 Dashboard Features
- **Blood Type Summary**: Visual cards showing inventory by blood type
- **Quick Stats**: Total units, available units, expiring units, expired units
- **Color-coded Alerts**: 
  - 🟢 Green: Healthy stock (10+ units)
  - 🟡 Yellow: Low stock (5-9 units)
  - 🔴 Red: Critical stock (<5 units)
- **Expiring Units Alert**: Shows units expiring within 5 days

### 🔍 Search & Filter
- **Blood Type Filter**: Dropdown to filter by specific blood types
- **Status Filter**: Available, Used, Expired, Quarantined
- **Date Range**: Filter by collection date or expiry date
- **Search Bar**: Search by Unit ID, Donor Name, or Reference Number
- **Real-time Results**: Instant filtering without page reload

### 📋 Inventory Table
- **Sortable Columns**: Click any column header to sort
- **Status Indicators**: Color-coded status badges
- **Donor Information**: Masked for privacy based on user role
- **Expiry Warnings**: Visual indicators for expiring units
- **Action Buttons**: View details, update status (role-dependent)

### 🔐 Security Features
- **Role-based Access Control (RBAC)**:
  - `super_admin`: Full access to all features
  - `inventory_manager`: Can add, edit, and manage inventory
  - `medical_staff`: Can view and update medical information
  - `viewer`: Read-only access
- **Audit Logging**: Every action is logged with timestamp and user
- **Data Masking**: Sensitive donor information hidden based on permissions
- **CSRF Protection**: All forms protected against cross-site request forgery

## File Structure

```
blood-donation-pwa/
├── admin_blood_inventory_redesigned.php    # Main inventory interface
├── includes/
│   └── BloodInventoryManagerEnhanced.php   # Backend logic class
├── sql/
│   └── enhanced_blood_inventory_migration.sql  # Database schema
├── migrate_enhanced_blood_inventory.php    # Migration script
└── ENHANCED_BLOOD_INVENTORY_GUIDE.md      # This documentation
```

## Installation

### 1. Database Setup
```bash
# Run the migration script
php migrate_enhanced_blood_inventory.php
```

### 2. Update Admin Navigation
Update `admin.php` to link to the new inventory page:
```php
// Replace the existing Blood Inventory link with:
<a class="nav-link" href="admin_blood_inventory_redesigned.php">
    <i class="fas fa-tint"></i> Blood Inventory
</a>
```

### 3. Configure User Roles
Ensure your admin users have appropriate roles set in the database:
```sql
-- Example role assignments
UPDATE admin_users SET role = 'super_admin' WHERE username = 'admin';
UPDATE admin_users SET role = 'inventory_manager' WHERE username = 'staff1';
```

## Usage Guide

### Adding Blood Units
1. Click "Add Unit" button (requires appropriate permissions)
2. Select donor from dropdown (only approved/served donors shown)
3. Select blood type
4. Set collection date (expiry date auto-calculated: +42 days)
5. Click "Add Unit" to save

### Managing Units
1. **View Details**: Click eye icon to see full unit information
2. **Update Status**: Click edit icon to change unit status
3. **Filter/Search**: Use filters and search bar to find specific units
4. **Export Data**: Click "Export CSV" to download filtered results

### Status Management
- **Available**: Unit ready for use
- **Used**: Unit has been issued to a patient
- **Expired**: Unit past expiry date (auto-updated daily)
- **Quarantined**: Unit failed screening or has issues

### Automated Features
- **Daily Expiry Check**: System automatically marks expired units
- **Low Stock Alerts**: Visual warnings when stock is below 5 units
- **Audit Logging**: All changes automatically logged
- **Auto-refresh**: Dashboard refreshes every 5 minutes

## Database Schema

### blood_inventory Table
```sql
- id: Primary key
- unit_id: Unique identifier (e.g., PRC-20241201-0001)
- donor_id: Foreign key to donors_new table
- blood_type: ENUM of blood types
- collection_date: When blood was collected
- expiry_date: When blood expires (collection_date + 42 days)
- status: available, used, expired, quarantined
- collection_center: Where blood was collected
- collection_staff: Who collected the blood
- test_results: JSON field for lab results
- location: Physical storage location
- notes: Additional notes
- created_at, updated_at: Timestamps
```

### blood_inventory_audit Table
```sql
- id: Primary key
- unit_id: Foreign key to blood_inventory
- action_type: Type of action performed
- description: Human-readable description
- details: JSON field with additional data
- admin_username: Who performed the action
- ip_address, user_agent: Security tracking
- created_at: When action was performed
```

## API Endpoints

### POST Actions
- `action=add_unit`: Add new blood unit
- `action=update_status`: Update unit status
- `action=update_blood_type`: Update blood type
- `action=get_unit_details`: Get detailed unit information

### GET Parameters
- `blood_type`: Filter by blood type
- `status`: Filter by status
- `date_from`, `date_to`: Date range filter
- `search`: Search term
- `page`: Pagination
- `export=csv`: Export to CSV

## Customization

### Adding New Blood Types
1. Update the ENUM in the database:
```sql
ALTER TABLE blood_inventory 
MODIFY COLUMN blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown', 'NewType');
```

2. Update the blood types array in the PHP code:
```php
$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown', 'NewType'];
```

### Modifying Expiry Period
Change the 42-day expiry period:
```php
// In BloodInventoryManagerEnhanced.php
$expiryDate = date('Y-m-d', strtotime($data['collection_date'] . ' +42 days'));
```

### Customizing Alerts
Modify low stock threshold:
```php
// In the dashboard summary logic
if ($count < 5) { // Change this number
    $class = 'critical';
}
```

## Troubleshooting

### Common Issues

1. **Permission Denied Errors**
   - Check user role in database
   - Verify RBAC permissions in code

2. **Unit ID Generation Conflicts**
   - Ensure unit_id column is UNIQUE
   - Check for duplicate generation logic

3. **Audit Log Not Working**
   - Verify blood_inventory_audit table exists
   - Check database permissions

4. **Export Not Working**
   - Check file permissions
   - Verify CSV headers match data

### Debug Mode
Enable debug logging by adding to the top of PHP files:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Performance Optimization

### Database Indexes
The system includes optimized indexes for:
- Unit ID lookups
- Blood type filtering
- Status filtering
- Date range queries
- Donor ID joins

### Caching
Consider implementing Redis caching for:
- Dashboard summary data
- Blood type counts
- Expiring units list

### Pagination
- Default: 20 units per page
- Configurable in the code
- Efficient LIMIT/OFFSET queries

## Security Considerations

1. **Input Validation**: All inputs validated and sanitized
2. **SQL Injection**: Prepared statements used throughout
3. **XSS Protection**: All output properly escaped
4. **CSRF Protection**: Tokens on all forms
5. **Role-based Access**: Granular permission system
6. **Audit Logging**: Complete action tracking

## Future Enhancements

1. **Barcode Scanning**: QR code generation and scanning
2. **Mobile App**: React Native mobile interface
3. **Real-time Notifications**: WebSocket updates
4. **Advanced Analytics**: Charts and reporting
5. **Integration**: Connect with hospital systems
6. **Automated Testing**: Unit and integration tests

## Support

For technical support or feature requests:
1. Check this documentation first
2. Review error logs in the application
3. Test with sample data
4. Contact the development team

---

**Version**: 1.0.0  
**Last Updated**: December 2024  
**Compatibility**: PHP 7.4+, MySQL 5.7+, Bootstrap 5.3+
