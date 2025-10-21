# 🩸 Blood Inventory Management System
## Philippine Red Cross Compliant | Data Privacy Act 2012 Compliant

### 📋 **System Overview**

This comprehensive Blood Inventory Management System follows Philippine Red Cross procedures and complies with the Data Privacy Act of 2012. It provides complete tracking, management, and reporting of blood units from collection to usage.

---

## 🚀 **Installation Instructions**

### **Step 1: Database Migration**
Run the migration script to set up the database:
```
http://localhost/blood-donation-pwa/migrate_blood_inventory.php
```

### **Step 2: Access the System**
Navigate to the Blood Inventory Management interface:
```
http://localhost/blood-donation-pwa/admin_blood_inventory.php
```

### **Step 3: Default Login**
- **Username:** `admin`
- **Password:** `password`
- **⚠️ IMPORTANT:** Change the default password immediately after first login!

---

## 🏗️ **System Architecture**

### **Database Tables Created:**

1. **`blood_inventory`** - Main inventory table
   - Unique unit IDs (PRC-[BloodType]-[YYYYMMDD]-[001])
   - Blood type, collection/expiry dates, status tracking
   - Test results stored in JSON format
   - Full audit trail with created_by/updated_by

2. **`blood_inventory_audit`** - Complete audit logging
   - Who, what, when for every action
   - IP address and user agent tracking
   - Old/new values comparison

3. **`admin_users`** - Role-based access control
   - 4 roles: super_admin, inventory_manager, medical_staff, viewer
   - Granular permissions system

4. **`blood_requests_inventory`** - Blood usage tracking
   - Links blood units to requests
   - FIFO (First In, First Out) implementation

### **Automated Features:**
- **Daily expiry check** - Automatic status updates
- **Stored procedures** - For complex operations
- **Database views** - For dashboard summaries

---

## 👥 **Role-Based Access Control**

### **Super Admin**
- ✅ Full system access
- ✅ View donor personal information
- ✅ Create, edit, delete blood units
- ✅ Update test results
- ✅ Access audit logs

### **Inventory Manager**
- ✅ Manage blood inventory
- ✅ View donor information
- ✅ Update test results
- ✅ Issue blood units
- ❌ Cannot delete records

### **Medical Staff**
- ✅ View inventory
- ✅ Update test results only
- ✅ View donor information
- ❌ Cannot create/delete units

### **Viewer**
- ✅ View inventory only
- ❌ No editing permissions
- ❌ Donor info masked for privacy

---

## 🔒 **Data Privacy Act 2012 Compliance**

### **Privacy Protection Measures:**

1. **Data Masking**
   - Donor personal info masked for unauthorized roles
   - Only shows `***` for sensitive data

2. **Access Logging**
   - Every access logged with timestamp
   - IP address and user agent tracking
   - Full audit trail for compliance

3. **Role-Based Visibility**
   ```php
   // Example: Conditional data display
   $donorFields = $this->hasPermission('view_donor_info') ? 
       "d.first_name, d.last_name, d.email" : 
       "'***' as first_name, '***' as last_name, '***' as email";
   ```

4. **Secure Data Storage**
   - Test results in encrypted JSON format
   - Foreign key constraints prevent data orphaning
   - Soft deletes where appropriate

---

## 📊 **Key Features**

### **FIFO Blood Issuance**
```php
// Automatic oldest-first selection
$stmt = $pdo->prepare("
    SELECT unit_id FROM blood_inventory 
    WHERE blood_type = ? AND status = 'available' 
    ORDER BY collection_date ASC 
    LIMIT 1
");
```

### **Automatic Expiry Management**
- Daily scheduled task updates expired units
- 35-day expiry for whole blood
- Visual alerts 5 days before expiry

### **Comprehensive Filtering**
- Blood type, status, date ranges
- Search by unit ID or donor reference
- Advanced sorting and pagination

### **Dashboard Analytics**
- Total units per blood type
- Low stock alerts (< 5 units)
- Expiring units warnings
- Usage statistics

---

## 🎯 **Philippine Red Cross Procedures Implemented**

### **Unit ID Format**
```
PRC-[BloodType]-[YYYYMMDD]-[001]
Examples:
- PRC-AP-20240115-001 (A+ collected on Jan 15, 2024)
- PRC-ON-20240120-002 (O- collected on Jan 20, 2024)
```

### **Blood Type Management**
- Standard ABO/Rh system (A+, A-, B+, B-, AB+, AB-, O+, O-)
- "Unknown" type for units pending lab screening
- Admin can update Unknown → confirmed type after testing

### **Status Workflow**
1. **Available** → Ready for issuance
2. **Used** → Issued to patient/hospital
3. **Expired** → Past expiry date (auto-updated)
4. **Quarantined** → Failed screening tests

### **Test Results Tracking**
```json
{
  "hiv": "negative",
  "hepatitis_b": "negative", 
  "hepatitis_c": "negative",
  "syphilis": "negative",
  "screening_date": "2024-01-15",
  "technician": "Lab Tech Name"
}
```

---

## 🔧 **Technical Implementation**

### **Backend (PHP)**
- **BloodInventoryManager.php** - Core business logic
- Object-oriented design with error handling
- PDO prepared statements for security
- Transaction support for data integrity

### **Frontend (Bootstrap 5)**
- Responsive design for mobile/desktop
- Real-time filtering and search
- Modal forms for data entry
- Color-coded status indicators

### **Database (MySQL)**
- Optimized indexes for performance
- Foreign key constraints for integrity
- JSON fields for flexible test results
- Views for dashboard summaries

---

## 📈 **Dashboard Features**

### **Summary Cards**
- Units per blood type with availability
- Color-coded low stock warnings
- Total inventory statistics

### **Alert System**
- 🔴 **Critical:** Units expiring in ≤ 2 days
- 🟡 **Warning:** Units expiring in 3-5 days  
- 🟠 **Low Stock:** < 5 units available

### **Visual Indicators**
- Status color coding (green=available, red=expired, etc.)
- Urgency borders for expiring units
- Progress bars for stock levels

---

## 🛡️ **Security Features**

### **Authentication & Authorization**
- Session-based admin authentication
- CSRF token protection on forms
- Role-based permission checks

### **Audit Logging**
```php
// Every action logged automatically
$this->logAudit($unitId, 'updated', $oldValues, $newValues);
```

### **Data Validation**
- Input sanitization and validation
- SQL injection prevention
- XSS protection with htmlspecialchars()

---

## 📱 **Usage Examples**

### **Adding a Blood Unit**
1. Click "Add Blood Unit" button
2. Enter donor ID, blood type, collection date
3. System generates unique unit ID automatically
4. Expiry date calculated (collection + 35 days)

### **Issuing Blood (FIFO)**
1. Request comes in for specific blood type
2. System automatically selects oldest available unit
3. Status updated to "used"
4. Issuance logged with recipient details

### **Updating Test Results**
1. Medical staff selects unit
2. Enters test results in JSON format
3. If tests fail → status becomes "quarantined"
4. All changes logged in audit trail

---

## 🔄 **Maintenance & Monitoring**

### **Daily Tasks (Automated)**
- Expiry status updates
- Low stock notifications
- Dashboard data refresh

### **Weekly Tasks (Manual)**
- Review audit logs
- Check expired unit disposal
- Verify test result completeness

### **Monthly Tasks**
- Generate compliance reports
- Review user access permissions
- Database maintenance and backup

---

## 📞 **Support & Troubleshooting**

### **Common Issues**

1. **"Insufficient permissions" error**
   - Check user role assignment
   - Verify admin_users table

2. **Unit ID generation fails**
   - Check database connection
   - Verify blood_inventory table

3. **Dashboard not loading**
   - Run migration script again
   - Check view creation

### **Logs Location**
- Application logs: `/logs/error.log`
- Audit logs: `blood_inventory_audit` table
- System logs: MySQL error logs

---

## ✅ **Compliance Checklist**

### **Philippine Red Cross Standards**
- ✅ Unique unit identification
- ✅ FIFO issuance protocol
- ✅ 35-day expiry tracking
- ✅ Complete test result documentation
- ✅ Proper status workflow

### **Data Privacy Act 2012**
- ✅ Role-based access control
- ✅ Data masking for unauthorized users
- ✅ Complete audit trail
- ✅ Secure data storage
- ✅ Access logging

### **System Requirements**
- ✅ Real-time inventory tracking
- ✅ Low stock alerts
- ✅ Expiry notifications
- ✅ Comprehensive reporting
- ✅ Mobile-responsive interface

---

**🎉 Your Blood Inventory Management System is now fully operational and compliant!**

For additional support or customization, refer to the source code documentation or contact your system administrator.
