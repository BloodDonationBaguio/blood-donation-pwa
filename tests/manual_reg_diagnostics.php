<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
function exists($p){ return file_exists($p) ? 'yes' : 'no'; }
function info($p){ return file_exists($p) ? (filesize($p).' bytes, mtime '.date('Y-m-d H:i:s', filemtime($p))) : 'missing'; }
function has($p,$pattern){ if(!file_exists($p)) return 'n/a'; $s=file_get_contents($p); return preg_match($pattern,$s)?'found':'not found'; }
function extract_form($p){ if(!file_exists($p)) return ['ok'=>false,'reason'=>'missing']; $html=file_get_contents($p);
 $chunk='';
 if(preg_match('/<div\s+class="card mb-4"\s+id="eligibilityCheck"[\s\S]*?<form[\s\S]*?id="donorForm"[\s\S]*?<\/form>/i',$html,$m)){ $chunk=$m[0]; }
 elseif(preg_match('/<form[\s\S]*?id="donorForm"[\s\S]*?<\/form>/i',$html,$m)){ $chunk=$m[0]; }
 if(!$chunk) return ['ok'=>false,'reason'=>'form chunk not matched'];
 $chunk=preg_replace('/action="[^"]*"/i','action="process_manual_donor.php"',$chunk);
 return ['ok'=>true,'html'=>$chunk]; }

$root=dirname(__DIR__);
$paths=[
 'donor_registration_root'=> $root.'/donor-registration.php',
 'donor_registration_sub'=> $root.'/blood-donation-pwa/donor-registration.php',
 'admin_enhanced'=> $root.'/admin_enhanced_donor_management.php',
 'process_manual'=> $root.'/process_manual_donor.php',
 'add_donor_tab_root'=> $root.'/includes/admin/tabs/add-donor.php',
 'add_donor_tab_sub'=> $root.'/blood-donation-pwa/includes/admin/tabs/add-donor.php',
 'admin_root'=> $root.'/admin.php',
 'admin_sub'=> $root.'/blood-donation-pwa/admin.php',
];

$report=[
 'exists'=>[
  'donor-registration (root)'=> exists($paths['donor_registration_root']),
  'donor-registration (subfolder)'=> exists($paths['donor_registration_sub']),
  'admin_enhanced_donor_management.php'=> exists($paths['admin_enhanced']),
  'process_manual_donor.php'=> exists($paths['process_manual']),
  'add-donor tab (root)'=> exists($paths['add_donor_tab_root']),
  'add-donor tab (subfolder)'=> exists($paths['add_donor_tab_sub']),
  'admin.php (root)'=> exists($paths['admin_root']),
  'admin.php (subfolder)'=> exists($paths['admin_sub']),
 ],
 'info'=>[
  'donor-registration (root)'=> info($paths['donor_registration_root']),
  'donor-registration (subfolder)'=> info($paths['donor_registration_sub']),
  'admin_enhanced_donor_management.php'=> info($paths['admin_enhanced']),
  'process_manual_donor.php'=> info($paths['process_manual']),
 ],
 'markers'=>[
  'admin_enhanced has manualRegistrationModal'=> has($paths['admin_enhanced'],'/id=\"manualRegistrationModal\"/i'),
  'admin_enhanced uses process_manual_donor.php'=> has($paths['admin_enhanced'],'/process_manual_donor\.php/i'),
  'add-donor (root) has eligibilityCheckAdmin'=> has($paths['add_donor_tab_root'],'/id=\"eligibilityCheckAdmin\"/i'),
  'add-donor (sub) has eligibilityCheckAdmin'=> has($paths['add_donor_tab_sub'],'/id=\"eligibilityCheckAdmin\"/i'),
  'admin (root) manual-register block present'=> has($paths['admin_root'],'/\$activeTab\s*===\s*\'manual-register\'/'),
  'admin (sub) manual-register block present'=> has($paths['admin_sub'],'/\$activeTab\s*===\s*\'manual-register\'/'),
 ],
 'extraction'=> extract_form(file_exists($paths['donor_registration_root'])?$paths['donor_registration_root']:$paths['donor_registration_sub']),
];

header('Content-Type: text/plain');
echo "Manual Registration Diagnostics\n";
echo str_repeat('=',32)."\n\n";
echo "Exists:\n"; foreach($report['exists'] as $k=>$v){ echo "- $k: $v\n"; }
echo "\nInfo:\n"; foreach($report['info'] as $k=>$v){ echo "- $k: $v\n"; }
echo "\nMarkers:\n"; foreach($report['markers'] as $k=>$v){ echo "- $k: $v\n"; }
echo "\nExtraction:\n"; if(!empty($report['extraction']['ok'])){ echo "- ok: yes\n"; echo "\n--- snippet begin ---\n".$report['extraction']['html']."\n--- snippet end ---\n"; } else { echo "- ok: no\n- reason: ".$report['extraction']['reason']."\n"; }
echo "\nHints:\n";
echo "- If subfolder files differ, production may serve submodule build.\n";
echo "- If manualRegistrationModal not found, admin_enhanced_donor_management.php is not updated or cached.\n";
echo "- If extraction failed, donor-registration.php form selectors changed; adjust parser.\n";
?>