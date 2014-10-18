<?php

define('BERLIOZ_SECRET', 'DUCHESSEFTW');
define('BERLIOZ_HOST', 'berlioz.gareste.fr');

function build_secure_url($path, $expires_in=60){
  // Set expiration timestamp
  $t = time() + $expires_in;

  // Build hash
  $contents = sprintf("%d:%s:%s", $t, $path, BERLIOZ_SECRET);
  $h = base64_encode(md5($contents, true));
  $h = str_replace(array('=', '+', '/'), array('', '-', '_'), $h);

  return sprintf("http://%s%s?md5=%s&expires=%d", BERLIOZ_HOST, $path, $h, $t);
}
