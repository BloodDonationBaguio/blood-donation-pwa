<?php
/**
 * Supabase Credential Extractor
 * This helps you get the exact credentials from your dashboard
 */

echo "🔍 GETTING YOUR SUPABASE CREDENTIALS\n";
echo "=====================================\n\n";

// Based on your screenshots, I can see your project details
echo "From your dashboard, I can see:\n";
echo "✅ Project: BloodDonationBaguio's Project\n";
echo "✅ Status: Healthy (all services running)\n";
echo "✅ No tables created yet (perfect for fresh start)\n\n";

echo "🎯 NOW LET'S GET YOUR EXACT CREDENTIALS:\n\n";

echo "STEP 1: Get Your Project URL\n";
echo "----------------------------\n";
echo "Look at your browser address bar RIGHT NOW.\n";
echo "You should see something like:\n";
echo "https://supabase.com/dashboard/project/ixgpdcvfwpqkgzvshh\n\n";
echo "YOUR PROJECT URL IS:\n";
echo "https://ixgpdcvfwpqkgzvshh.supabase.co\n\n";

echo "✅ COPY THIS: https://ixgpdcvfwpqkgzvshh.supabase.co\n\n";

echo "STEP 2: Get Your Service Role Key\n";
echo "-----------------------------------\n";
echo "1. In your Supabase dashboard, look for 'Settings' in the left sidebar\n";
echo "2. Click on 'API' under Settings\n";
echo "3. Look for 'Service Role Key' (it will be a long string starting with 'eyJ')\n";
echo "4. Click 'Reveal' to see the full key\n";
echo "5. COPY the entire key\n\n";

echo "STEP 3: Create Your .env File\n";
echo "-----------------------------\n";
echo "1. In your project folder, create a new file called '.env'\n";
echo "2. Add these 2 lines (replace with your actual values):\n\n";
echo "SUPABASE_URL=https://ixgpdcvfwpqkgzvshh.supabase.co\n";
echo "SUPABASE_SERVICE_ROLE_KEY=your-service-role-key-here\n\n";

echo "STEP 4: Test Your Connection\n";
echo "----------------------------\n";
echo "After you create the .env file, run:\n";
echo "php test_supabase_connection.php\n\n";

echo "💡 TIP: The Service Role Key is like a master password - keep it secret!\n";
echo "💡 TIP: Once this works, you'll be FREE from Render payments! 🎉\n";

?>