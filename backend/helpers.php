<?php
// Helper untuk escape output HTML
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Helper untuk generate OTP random 6 digit
function generateOtp($length = 6) {
    $digits = '0123456789';
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= $digits[random_int(0, strlen($digits) - 1)];
    }
    return $otp;
}

// Helper untuk cek expired OTP
function isOtpExpired($expiredAt) {
    return strtotime($expiredAt) < time();
}