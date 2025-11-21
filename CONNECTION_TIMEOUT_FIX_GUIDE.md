# Connection Timeout Fix Guide

## Overview
This guide addresses the connection timeout issues shown in your monitoring dashboard and provides comprehensive fixes for your blood donation PWA.

## Issues Identified
1. **PostgreSQL sequence issues** - Fixed in `fix_sequences.sql`
2. **Database connection timeouts** - Addressed with optimized connection handling
3. **Monitoring IP allowlist** - Updated firewall rules needed
4. **Health check improvements** - Enhanced monitoring capabilities

## Files Created/Modified

### 1. Enhanced SQL Fix Script
- **File**: `fix_sequences.sql`
- **Purpose**: Fixes PostgreSQL sequences for donor_notes and admin_audit_log tables
- **Improvements**: Added table existence checks and better error handling

### 2. Connection Diagnostic Tool
- **File**: `connection_diagnostic.php`
- **Purpose**: Comprehensive database and network connectivity testing
- **Usage**: Access via browser to diagnose connection issues

### 3. Connection Optimization Script
- **File**: `optimize_connections.php`
- **Purpose**: Optimizes database connection settings to reduce timeouts
- **Features**: Performance testing, connection pooling optimization

### 4. Enhanced Health Check
- **File**: `health.php` (modified)
- **Purpose**: Improved health endpoint with database connectivity testing
- **Benefits**: Better monitoring integration, faster issue detection

### 5. Monitoring IP Configuration
- **File**: `monitoring_ip_config.php`
- **Purpose**: Generates firewall rules for monitoring services
- **Supports**: UptimeRobot, Pingdom, Datadog, and other monitoring services

## Deployment Steps

### Step 1: Fix Database Sequences
1. Open Supabase SQL Editor
2. Copy and paste the content from `fix_sequences.sql`
3. Execute the script
4. Verify no errors in the output

### Step 2: Test Connection Performance
1. Access `connection_diagnostic.php` in your browser
2. Review the diagnostic results
3. Address any issues identified in the recommendations

### Step 3: Optimize Database Connections
1. Access `optimize_connections.php` in your browser
2. Review the optimization results
3. Note any performance improvements

### Step 4: Update Monitoring Configuration
1. Access `monitoring_ip_config.php` in your browser
2. Copy the appropriate firewall rules for your hosting provider
3. Apply the rules to your server/hosting configuration

### Step 5: Verify Health Check
1. Test the enhanced health check at `/health.php`
2. Ensure it returns proper status codes
3. Update your monitoring service to use this endpoint

## Firewall Configuration

### For Apache Servers (.htaccess)
```apache
# Allow monitoring services
<RequireAll>
    Require ip 69.162.124.224/28
    Require ip 63.143.42.240/28
    # ... (see monitoring_ip_config.php for complete list)
</RequireAll>
```

### For Nginx Servers
```nginx
location /health {
    allow 69.162.124.224/28;
    allow 63.143.42.240/28;
    # ... (see monitoring_ip_config.php for complete list)
    deny all;
}
```

### For iptables (Linux)
```bash
# Allow UptimeRobot IPs
iptables -A INPUT -s 69.162.124.224/28 -p tcp --dport 80 -j ACCEPT
iptables -A INPUT -s 69.162.124.224/28 -p tcp --dport 443 -j ACCEPT
# ... (see monitoring_ip_config.php for complete list)

# Save rules
iptables-save > /etc/iptables/rules.v4
```

## Monitoring Service Configuration

### UptimeRobot
- Update your monitor URL to use `/health.php`
- Set check interval to 5 minutes
- Configure alert thresholds based on response time

### Expected Health Check Response
```json
{
    "status": "ok",
    "timestamp": "2025-11-21T06:16:00+00:00",
    "server_time": "2025-11-21 14:16:00",
    "checks": {
        "php": "ok",
        "database": "ok"
    },
    "database_response_time": 45.2
}
```

## Performance Optimizations Applied

1. **Connection Timeout Settings**
   - Set statement_timeout to 30 seconds
   - Configured TCP keepalive settings
   - Optimized work_mem for web workload

2. **Database Connection Pooling**
   - Implemented connection reuse
   - Added connection health checks
   - Optimized PDO settings

3. **Health Check Improvements**
   - Added database connectivity testing
   - Implemented proper HTTP status codes
   - Enhanced error reporting

## Troubleshooting

### If Connection Timeouts Persist
1. Check server resources (CPU, memory)
2. Review database connection limits
3. Consider implementing connection pooling
4. Check network latency to Supabase

### If Health Check Fails
1. Verify environment variables are set
2. Check database credentials
3. Test network connectivity to Supabase
4. Review error logs

### Common Issues
- **SSL Certificate Issues**: Ensure sslmode=require is working
- **Firewall Blocking**: Verify monitoring IPs are whitelisted
- **Database Overload**: Check active connection count
- **Network Latency**: Consider geographic proximity to database

## Verification Commands

```bash
# Test health endpoint
curl -I https://www.blooddonationbaguio.com/health.php

# Check database connectivity
php connection_diagnostic.php

# Test optimization script
php optimize_connections.php

# Generate monitoring IP rules
php monitoring_ip_config.php
```

## Next Steps

1. **Monitor Performance**: Watch connection times over the next 24 hours
2. **Update Alerts**: Configure monitoring thresholds based on new baseline
3. **Regular Maintenance**: Run optimization script weekly
4. **Backup Strategy**: Ensure database sequences are included in backups

## Support

If issues persist after implementing these fixes:
1. Check the diagnostic output from `connection_diagnostic.php`
2. Review server error logs
3. Monitor database performance metrics
4. Consider upgrading database resources if needed

---

**Last Updated**: November 21, 2025
**Version**: 1.0
**Status**: Ready for deployment
