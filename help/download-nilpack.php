<?php

$owner = $_ENV['OWNER'] ?? '';
if (empty($owner)) {
    $remote = shell_exec('git remote get-url origin');
    preg_match('#github\.com[:/]([^/]+)/#', $remote, $matches);
    $owner = $matches[1] ?? 'nilpack';
}

$tag = $_ENV['TAG'] ?? 'latest';

if ($tag === 'latest') {
    $ch = curl_init("https://api.github.com/repos/{$owner}/nilpack/releases/latest");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'nilpack-download');
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    $tag = $data['tag_name'] ?? 'v1.0.0';
}

echo "Downloading nilpack.phar from release $tag...\n";

$ch = curl_init("https://github.com/{$owner}/nilpack/releases/download/{$tag}/nilpack.phar");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$phar = curl_exec($ch);
curl_close($ch);

file_put_contents('nilpack.phar', $phar);
chmod('nilpack.phar', 0755);

echo "Download complete: nilpack.phar\n";