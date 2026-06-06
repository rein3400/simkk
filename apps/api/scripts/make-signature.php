<?php
/**
 * One-shot CLI: render placeholder signature PNGs for manajer and kasir
 * using PHP GD. Run from apps/api root:
 *   php scripts/make-signature.php
 */

declare(strict_types=1);

$outDir = __DIR__ . '/../public/signatures';
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

foreach (['manajer', 'kasir'] as $role) {
    $im = imagecreatetruecolor(200, 80);
    imagesavealpha($im, true);

    $white  = imagecolorallocate($im, 255, 255, 255);
    $black  = imagecolorallocate($im, 0, 0, 0);
    $shadow = imagecolorallocate($im, 80, 80, 80);
    $trans  = imagecolorallocatealpha($im, 255, 255, 255, 127);

    imagefill($im, 0, 0, $trans);

    // Two wavy lines for a hand-written feel. Different amplitude/phase per role.
    $phase   = $role === 'manajer' ? 0.0 : M_PI / 3;
    $amp     = $role === 'manajer' ? 14   : 12;
    $yBase   = $role === 'manajer' ? 42   : 45;
    $yBase2  = $yBase + 8;

    // Faint shadow
    for ($x = 10; $x <= 190; $x += 0.5) {
        $y = $yBase + $amp * sin(($x / 22) + $phase) + 1;
        imagesetpixel($im, (int) $x, (int) $y, $shadow);
    }
    // Main stroke
    for ($x = 10; $x <= 190; $x += 0.5) {
        $y = $yBase + $amp * sin(($x / 22) + $phase);
        imagesetpixel($im, (int) $x, (int) $y, $black);
    }
    // Second pass — slight offset
    for ($x = 20; $x <= 180; $x += 0.5) {
        $y = $yBase2 + ($amp - 4) * sin(($x / 18) + $phase + 0.7);
        imagesetpixel($im, (int) $x, (int) $y, $black);
    }

    // Optional decorative loop for the kasir signature.
    if ($role === 'kasir') {
        imageellipse($im, 100, 35, 26, 18, $black);
    }

    $path = "{$outDir}/{$role}.png";
    imagepng($im, $path);
    imagedestroy($im);

    fwrite(STDOUT, "wrote {$path}\n");
}
