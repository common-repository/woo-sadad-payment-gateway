<?php

function getChecksumFromString($str, $key) {
    
    $salt = generateSalt_e(4);
    $finalString = $str . "|" . $salt;
    $hash = hash("sha256", $finalString);
    $hashString = $hash . $salt;
    $checksum = encrypt_e($hashString, $key);
    return $checksum;
}

function generateSalt_e($length) {
    $random = "";
    srand((double) microtime() * 1000000);

    $data = "AbcDE123IJKLMN67QRSTUVWXYZ";
    $data .= "aBCdefghijklmn123opq45rs67tuv89wxyz";
    $data .= "0FGH45OP89";
    for ($i = 0; $i < $length; $i++) {
        $random .= substr($data, (rand() % (strlen($data))), 1);
    }
    return $random;
}

function encrypt_e($input, $ky) {
    $ky = html_entity_decode($ky);
    $iv = "@@@@&&&&####$$$$";
    $data = openssl_encrypt($input, "AES-128-CBC", $ky, 0, $iv);
    return $data;
}

function verifychecksum_eFromStr($str, $key, $checksumvalue) {
    $sadad_hash = decrypt_e($checksumvalue, $key);
    $salt = substr($sadad_hash, -4);

    $finalString = $str . "|" . $salt;

    $website_hash = hash("sha256", $finalString);
    $website_hash .= $salt;

    $validFlag = "FALSE";
    if ($website_hash == $sadad_hash) {
        $validFlag = "TRUE";
    } else {
        $validFlag = "FALSE";
    }
    return $validFlag;
}

function decrypt_e($crypt, $ky) {
    $ky = html_entity_decode($ky);
    $iv = "@@@@&&&&####$$$$";
    $data = openssl_decrypt($crypt, "AES-128-CBC", $ky, 0, $iv);
    return $data;
}
