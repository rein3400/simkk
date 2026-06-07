<?php
$img = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAAAAADhZOFXAAAADklEQVR4nGP4DwUMlDEA98A/wbI0QbsAAAAASUVORK5CYII=');
echo 'Bytes: ' . strlen($img) . PHP_EOL;
echo 'Header: ' . bin2hex(substr($img, 0, 8)) . PHP_EOL;
$info = @getimagesizefromstring($img);
echo 'Result: ';
var_dump($info);
