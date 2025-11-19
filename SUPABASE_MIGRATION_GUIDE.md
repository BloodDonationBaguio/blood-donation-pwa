# 🚀 Blood Donation PWA - Supabase Migration Guide

## Overview
This guide helps you migrate your blood donation PWA from Render PostgreSQL to Supabase (FREE!) to avoid payment issues.

## 📋 Migration Steps

### Step 1: Get Your Supabase Credentials
1. Go to [Supabase Dashboard](https://app.supabase.com)
2. Select your project ("BloodDonationBaguio's Project")
3. Navigate to **Settings > Database**
4. Copy these values:
   - **Project URL**: `https://[your-project-id].supabase.co`
   - **Service Role Key**: (Keep this secret!)

### Step 2: Set Environment Variables
Create a `.env` file in your project root with:

```env
# Supabase Configuration
SUPABASE_URL=https://[your-project-id].supabase.co
SUPABASE_SERVICE_ROLE_KEY=[your-service-role-key]

# Optional: Keep Render as fallback
DATABASE_URL=[your-render-database-url]
```

### Step 3: Apply Database Schema to Supabase
Run the migration SQL in Supabase:

```bash
# Apply the schema
php supabase_apply_migration.php
```

Or manually copy and run the SQL from `supabase_migration.sql` in Supabase SQL Editor.

### Step 4: Migrate Your Data (Optional)
If you want to migrate existing data from Render:

```bash
# Run the migration script
php migrate_to_supabase.php
```

⚠️ **Backup First**: This will copy all your data from Render to Supabase.

### Step 5: Update Your Application
Replace your database includes:

**OLD:** `require_once 'db.php';` or `require_once 'db_production.php';`

**NEW:** `require_once 'db_universal.php';`

The new `db_universal.php` automatically detects and uses:
1. **Supabase** (if SUPABASE_URL is set)
2. **Render** (if DATABASE_URL is set)
3. **Local MySQL/SQLite** (fallback)

### Step 6: Test Your Application
1. Visit your application
2. Test donor registration
3. Test admin login
4. Check blood inventory
5. Verify all features work

## 🔧 Files Created

| File | Purpose |
|------|---------|
| `supabase_migration.sql` | Database schema for Supabase |
| `supabase_db.php` | Supabase connection configuration |
| `migrate_to_supabase.php` | Data migration script |
| `db_universal.php` | Universal database connector |
| `.env.supabase.example` | Example environment configuration |

## 📊 Benefits of Supabase vs Render

| Feature | Render | Supabase (Free) |
|---------|--------|-----------------|
| **Database** | PostgreSQL (Paid) | PostgreSQL (FREE) |
| **Storage** | Limited | 1GB FREE |
| **Auth** | Manual | Built-in FREE |
| **Real-time** | Manual | Built-in FREE |
| **API** | Manual | Auto-generated FREE |
| **Bandwidth** | Limited | 5GB/month FREE |

## 🚨 Important Notes

1. **Service Role Key**: Keep this secret! Never expose it in client-side code.
2. **Data Migration**: Test migration on a copy first.
3. **Backup**: Always backup your Render data before migration.
4. **Testing**: Test thoroughly after migration.
5. **Rollback**: Keep Render connection as fallback initially.

## 🔍 Troubleshooting

### Connection Issues
```bash
# Check if Supabase credentials are set
echo $SUPABASE_URL
echo $SUPABASE_SERVICE_ROLE_KEY
```

### Database Errors
- Check `logs/supabase_error.log` for detailed errors
- Verify your Supabase project is active
- Check network connectivity to Supabase

### Migration Issues
- Ensure all tables exist in Render before migration
- Check for duplicate data conflicts
- Verify data types match between databases

## 📞 Need Help?

1. Check Supabase documentation: https://supabase.com/docs
2. Review error logs in `logs/` directory
3. Test connection with `supabase_db.php` directly

## ✅ Migration Checklist

- [ ] Supabase project created
- [ ] Environment variables set
- [ ] Database schema applied
- [ ] Data migrated (if needed)
- [ ] Application updated to use `db_universal.php`
- [ ] All features tested
- [ ] Render account cancelled (after successful migration)

---

**🎉 Congratulations!** You're now running on FREE Supabase infrastructure with:
- ✅ PostgreSQL database
- ✅ Built-in authentication (ready to implement)
- ✅ Real-time capabilities
- ✅ Auto-generated APIs
- ✅ 1GB storage
- ✅ 5GB monthly bandwidth

Your blood donation PWA is now cost-free and ready to scale! 🚀