# Admin Update Guide - Blood Inventory Management

## 🎯 **How Admins Update Blood Units**

### **📋 Update Methods Available**

## **1. Update Unit Status**

### **Step-by-Step Process:**
1. **Access the System**
   - Go to Admin Panel → Blood Inventory
   - Or directly: `admin_blood_inventory_complete.php`

2. **Find the Unit**
   - Use filters to locate the unit
   - Search by Unit ID, Donor Name, or Reference Code
   - Or browse through the inventory table

3. **Update Status**
   - Click the **Edit** button (pencil icon) in the Actions column
   - Select new status from dropdown:
     - **Available** → **Used** (when issued to patient)
     - **Available** → **Expired** (when past expiry date)
     - **Available** → **Quarantined** (when failed screening)
   - Enter reason/notes for the change
   - Click **Update Status**

### **Status Options:**
- **Available**: Unit ready for use
- **Used**: Unit has been issued to a patient
- **Expired**: Unit past expiry date (42 days)
- **Quarantined**: Unit failed screening or has issues

## **2. Update Blood Type**

### **When to Use:**
- After lab screening confirms blood type
- When blood type was initially marked as "Unknown"

### **Process:**
1. **Find the Unit** with "Unknown" blood type
2. **Click Edit** button
3. **Select Confirmed Blood Type** from dropdown
4. **Enter Lab Results** or confirmation notes
5. **Click Update**

### **Blood Type Options:**
- A+, A-, B+, B-, AB+, AB-, O+, O-
- Only available for units with "Unknown" status

## **3. Add New Blood Unit**

### **Process:**
1. **Click "Add Unit"** button (green button)
2. **Select Donor** from dropdown
   - Shows: Name, Reference Code, Blood Type
   - Only shows eligible donors (approved/served status)
3. **Set Collection Date** (defaults to today)
4. **Set Collection Site** (defaults to "Main Center")
5. **Set Storage Location** (defaults to "Storage A")
6. **Click "Add Unit"**

### **Auto-Detection:**
- Blood type automatically detected from donor
- Expiry date calculated (42 days from collection)
- Unit ID generated automatically

## **4. Delete/Archive Units**

### **When to Use:**
- Unit is contaminated or unusable
- Unit has been disposed of
- Administrative cleanup

### **Process:**
1. **Find the Unit** to delete
2. **Click Delete** button (trash icon)
3. **Confirm Deletion** in popup
4. **Enter Reason** for deletion
5. **Click Confirm**

### **Safety Measures:**
- Confirmation required before deletion
- Reason must be provided
- Complete audit trail maintained

## **🔍 Visual Interface Guide**

### **Main Dashboard:**
```
┌─────────────────────────────────────────────────────────────┐
│  Blood Inventory Management                                 │
├─────────────────────────────────────────────────────────────┤
│  [Total: 4] [Available: 2] [Expiring: 0] [Expired: 0]     │
├─────────────────────────────────────────────────────────────┤
│  Filters: [Blood Type ▼] [Status ▼] [Search...] [Filter]  │
│  Actions: [Add Unit] [Export CSV]                          │
├─────────────────────────────────────────────────────────────┤
│  Unit ID    │ Blood │ Donor │ Collection │ Expiry │ Status │ Actions │
│  PRC-001    │ A+    │ John  │ 2024-12-01│ 2025-01-12│ Available│ [👁️][✏️][🗑️] │
│  PRC-002    │ O+    │ Jane  │ 2024-12-01│ 2025-01-12│ Available│ [👁️][✏️][🗑️] │
└─────────────────────────────────────────────────────────────┘
```

### **Update Status Modal:**
```
┌─────────────────────────────────────┐
│  Update Unit Status                 │
├─────────────────────────────────────┤
│  New Status: [Available ▼]         │
│  Reason/Notes:                     │
│  ┌─────────────────────────────────┐ │
│  │ Enter reason for status change │ │
│  └─────────────────────────────────┘ │
│  [Cancel] [Update Status]          │
└─────────────────────────────────────┘
```

## **🎯 Role-Based Permissions**

### **Super Admin:**
- ✅ Full access to all features
- ✅ Can add, edit, delete any unit
- ✅ Can view all donor PII
- ✅ Can access all audit logs

### **Inventory Manager:**
- ✅ Can add, edit, delete units
- ✅ Can update status and blood type
- ✅ Cannot view donor PII
- ✅ Can access audit logs

### **Medical Staff:**
- ✅ Can view donor PII
- ✅ Can update medical data
- ✅ Can update blood type
- ✅ Cannot delete units

### **Viewer:**
- ✅ Read-only access
- ✅ Cannot make any changes
- ✅ Donor PII masked
- ✅ Can view audit logs

## **📊 Real-Time Updates**

### **What Happens When You Update:**
1. **Database Updated** immediately
2. **Audit Log Created** with:
   - Who made the change
   - What was changed
   - When it was changed
   - Old and new values
   - IP address and browser info
3. **Page Refreshes** to show updated data
4. **Alerts Updated** if status affects alerts

### **Audit Trail Example:**
```
Action: Status Updated
Description: Status changed from Available to Used
Old Values: {"status": "available"}
New Values: {"status": "used", "reason": "Issued to patient"}
Admin: admin_user
IP: 192.168.1.100
Time: 2024-12-01 14:30:25
```

## **🚨 Alerts and Monitoring**

### **Automatic Alerts:**
- **Expiring Units**: Units expiring within 5 days
- **Low Stock**: Blood types with less than 5 units
- **System Status**: Database connectivity issues

### **Visual Indicators:**
- **Green Badge**: Available units
- **Red Badge**: Expired units
- **Orange Badge**: Quarantined units
- **Gray Badge**: Used units
- **Yellow Background**: Units expiring soon

## **💡 Best Practices**

### **Before Updating:**
1. **Verify Unit Details** - Check unit ID and donor info
2. **Check Expiry Date** - Ensure unit is still valid
3. **Review Donor Status** - Confirm donor eligibility
4. **Document Changes** - Always provide reason/notes

### **After Updating:**
1. **Verify Changes** - Check that updates are correct
2. **Review Audit Log** - Confirm change was logged
3. **Check Alerts** - See if any new alerts were triggered
4. **Update Records** - Update any physical records if needed

## **🔧 Troubleshooting**

### **Common Issues:**
1. **"Insufficient Permissions"**
   - Check your user role
   - Contact admin to update permissions

2. **"Unit Not Found"**
   - Verify unit ID is correct
   - Check if unit was recently deleted

3. **"Donor Not Eligible"**
   - Check donor status
   - Ensure donor is approved or served

4. **"Status Update Failed"**
   - Check if unit is already in that status
   - Verify reason/notes are provided

### **Getting Help:**
- Check the audit log for error details
- Contact system administrator
- Review user permissions
- Check system alerts

## **📱 Mobile-Friendly**

### **Responsive Design:**
- Works on all devices (desktop, tablet, mobile)
- Touch-friendly buttons and forms
- Optimized for small screens
- Easy navigation on mobile

---

## **🎉 Ready to Use!**

The complete blood inventory management system is now ready for admin use with full update capabilities, security controls, and compliance features!

**Access**: Admin Panel → Blood Inventory  
**URL**: `admin_blood_inventory_complete.php`
