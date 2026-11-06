<?php

@mkdir(__DIR__, 0755, true);

for ($i = 1; $i <= 5; $i++) {
    $im = imagecreatetruecolor(800, 600);
    $bg = imagecolorallocate($im, 30 * $i, 80 + (20 * $i), 150);
    imagefill($im, 0, 0, $bg);
    $tc = imagecolorallocate($im, 255, 255, 255);
    imagestring($im, 5, 50, 50, "ERE Test Property Photo #{$i}", $tc);
    imagejpeg($im, __DIR__ . "/photo_{$i}.jpg", 85);
    imagedestroy($im);
}

// Generate valid PDF
$pdfContent = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R/Resources<<>>>>endobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000052 00000 n \n0000000101 00000 n \ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n178\n%%EOF\n";
file_put_contents(__DIR__ . '/sample_deed.pdf', $pdfContent);

echo "Fixtures generated successfully.\n";

