<?php
// Load the source image
$logoUrl = 'https://benguetredcross.com/wp-content/uploads/2023/03/Logo_Philippine_Red_Cross-1536x1536.png';
$sourceImage = imagecreatefrompng($logoUrl);

if (!$sourceImage) {
    die('Failed to load source image');
}

// Create output directory if it doesn't exist
$outputDir = __DIR__ . '/images';
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// Sizes for different PWA icons
$sizes = [192, 256, 384, 512];

foreach ($sizes as $size) {
    $outputFile = "{$outputDir}/icon-{$size}x{$size}.png";
    
    // Create a blank image with transparent background
    $canvas = imagecreatetruecolor($size, $size);
    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefill($canvas, 0, 0, $transparent);
    imagesavealpha($canvas, true);
    
    // Resize the logo to fit within the canvas
    $logoWidth = imagesx($sourceImage);
    $logoHeight = imagesy($sourceImage);
    
    // Calculate aspect ratio
    $ratio = min($size / $logoWidth, $size / $logoHeight);
    $newWidth = (int)($logoWidth * $ratio);
    $newHeight = (int)($logoHeight * $ratio);
    
    // Center the image
    $x = (int)(($size - $newWidth) / 2);
    $y = (int)(($size - $newHeight) / 2);
    
    // Resample and copy
    imagecopyresampled(
        $canvas, $sourceImage,
        $x, $y, 0, 0,
        $newWidth, $newHeight,
        $logoWidth, $logoHeight
    );
    
    // Save the image
    imagepng($canvas, $outputFile, 9);
    imagedestroy($canvas);
    
    echo "Generated: {$outputFile}\n";
}

// Create a screenshot placeholder
$screenshot = imagecreatetruecolor(1280, 720);
$bgColor = imagecolorallocate($screenshot, 220, 53, 69); // Red color
$textColor = imagecolorallocate($screenshot, 255, 255, 255);
imagefill($screenshot, 0, 0, $bgColor);
$text = 'Blood Donation System';
$fontSize = 48;
$font = 5; // Built-in font
$textWidth = imagefontwidth($font) * strlen($text);
$textX = (1280 - $textWidth) / 2;
$textY = 360 - (imagefontheight($font) / 2);
imagestring($screenshot, $font, $textX, $textY, $text, $textColor);
imagepng($screenshot, "{$outputDir}/screenshot1.png");

echo "Generated: {$outputDir}/screenshot1.png\n";

// Clean up
imagedestroy($sourceImage);
imagedestroy($screenshot);

echo "\nAll icons and screenshots have been generated successfully!\n";
?>
