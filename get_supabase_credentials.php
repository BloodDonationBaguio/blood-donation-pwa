<?php
/**
 * Supabase Credential Helper
 * This script helps you get your Supabase credentials step by step
 */

echo "🎯 SUPABASE CREDENTIAL HELPER\n";
echo "============================\n\n";

echo "Step 1: Get your Supabase Project URL\n";
echo "-------------------------------------\n";
echo "1. Open: https://app.supabase.com\n";
echo "2. Click your project: 'BloodDonationBaguio's Project'\n";
echo "3. In the left sidebar, click: Settings → Database\n";
echo "4. Look for: 'Project URL' (starts with https://)\n";
echo "5. Copy the full URL (like: https://xyz123.supabase.co)\n\n";

echo "Step 2: Get your Service Role Key\n";
echo "----------------------------------\n";
echo "1. Still in Settings, click: API\n";
echo "2. Look for: 'Service Role Key' (long string of letters/numbers)\n";
echo "3. Click the 'Reveal' button\n";
echo "4. Copy the entire key (starts with 'eyJ')\n\n";

echo "Step 3: Create your .env file\n";
echo "----------------------------\n";
echo "1. Create a new file called '.env' in your project folder\n";
echo "2. Add these 2 lines (replace with your actual values):\n\n";
echo "SUPABASE_URL=https://your-project-id.supabase.co\n";
echo "SUPABASE_SERVICE_ROLE_KEY=your-service-role-key-here\n\n";

echo "Step 4: Test the connection\n";
echo "--------------------------\n";
echo "After you create the .env file, run this command:\n";
echo "php test_supabase_connection.php\n\n";

echo "That's it! 🎉\n";
echo "Once you have your credentials, I'll help you complete the migration!\n";

echo "\n💡 TIP: The Service Role Key gives full database access - keep it secret!\n";

?>