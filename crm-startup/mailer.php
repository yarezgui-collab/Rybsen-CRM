<?php
// ============================================================
// mailer.php — Envoi d'emails résilient (Startup.TN)
//
// Objectif : aucune fonction d'envoi ne doit JAMAIS provoquer une
// erreur fatale (page blanche). Sur hébergement mutualisé Hostinger,
// mail() est souvent filtré/spam ; le canal fiable est le SMTP
// authentifié. Ce module tente SMTP si configuré, sinon mail() avec
// des en-têtes corrects, et avale toute erreur (retourne false).
//
// Configuration (dans config.php, hors dépôt) — tout est optionnel :
//   define('SMTP_HOST', 'smtp.hostinger.com');
//   define('SMTP_PORT', 465);            // 465 = SSL, 587 = STARTTLS
//   define('SMTP_USER', 'noreply@startup.rybsen.fr');
//   define('SMTP_PASS', '••••••••');
//   define('SMTP_SECURE', 'ssl');        // 'ssl' ou 'tls' (auto sinon)
//   define('MAIL_FROM', 'noreply@startup.rybsen.fr');
//   define('MAIL_FROM_NAME', 'Startup.TN');
// Sans SMTP_*, on retombe sur mail() avec MAIL_FROM comme expéditeur.
// ============================================================

if (!function_exists('stn_base_url')) {
    function stn_base_url(): string
    {
        if (defined('APP_URL') && APP_URL) return rtrim(APP_URL, '/');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'startup.rybsen.fr';
        return $scheme . '://' . $host;
    }
}

if (!function_exists('stn_mail_from')) {
    function stn_mail_from(): array
    {
        $addr = (defined('MAIL_FROM') && MAIL_FROM) ? MAIL_FROM
              : ((defined('SMTP_USER') && SMTP_USER) ? SMTP_USER
              : 'noreply@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'startup.rybsen.fr'));
        $name = (defined('MAIL_FROM_NAME') && MAIL_FROM_NAME) ? MAIL_FROM_NAME : 'Startup.TN';
        return [$addr, $name];
    }
}

if (!function_exists('stn_mail')) {
    /**
     * Envoi bas niveau. Ne lève jamais d'exception. Retourne true si l'envoi
     * a été accepté par le transport, false sinon.
     */
    function stn_mail(string $to, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        // Garde anti-injection d'en-têtes
        if ($to === '' || preg_match('/[\r\n]/', $to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Hook de test : simule un échec d'envoi (défini uniquement par le harnais)
        if (defined('MAIL_FORCE_FAIL') && MAIL_FORCE_FAIL) {
            return false;
        }

        // Hook de test : on journalise au lieu d'envoyer (utilisé par le harnais local).
        // Le corps (texte) est inclus pour permettre aux tests de lire le code/lien ;
        // MAIL_TEST_LOG n'est JAMAIS défini en production.
        if (defined('MAIL_TEST_LOG') && MAIL_TEST_LOG) {
            $bodyForLog = preg_replace('/\s+/', ' ', $textBody !== '' ? $textBody : strip_tags($htmlBody));
            @file_put_contents(
                MAIL_TEST_LOG,
                date('c') . " TO=$to SUBJECT=" . preg_replace('/\s+/', ' ', $subject) . " BODY=" . $bodyForLog . "\n",
                FILE_APPEND
            );
            return true;
        }

        try {
            if (defined('SMTP_HOST') && SMTP_HOST && defined('SMTP_USER') && defined('SMTP_PASS')) {
                return stn_smtp_send($to, $subject, $htmlBody, $textBody);
            }
            return stn_mail_native($to, $subject, $htmlBody, $textBody);
        } catch (\Throwable $e) {
            // Dernier recours : mail() natif, en avalant toute erreur
            try {
                return stn_mail_native($to, $subject, $htmlBody, $textBody);
            } catch (\Throwable $e2) {
                error_log('[stn_mail] échec total: ' . $e2->getMessage());
                return false;
            }
        }
    }
}

if (!function_exists('stn_mail_native')) {
    function stn_mail_native(string $to, string $subject, string $html, string $text): bool
    {
        [$from, $fromName] = stn_mail_from();
        $boundary = '=_' . bin2hex(random_bytes(8));
        $text = $text !== '' ? $text : trim(preg_replace('/\s+/', ' ', strip_tags($html)));

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>';
        $headers[] = 'Reply-To: ' . $from;
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $body  = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text)) . "\r\n";
        $body .= "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "\r\n--$boundary--";

        // -f fixe l'enveloppe (Return-Path), améliore la délivrabilité sur Hostinger
        return @mail($to, $encodedSubject, $body, implode("\r\n", $headers), '-f' . $from);
    }
}

if (!function_exists('stn_smtp_send')) {
    function stn_smtp_send(string $to, string $subject, string $html, string $text): bool
    {
        $host   = SMTP_HOST;
        $port   = defined('SMTP_PORT') ? (int) SMTP_PORT : 465;
        $user   = SMTP_USER;
        $pass   = SMTP_PASS;
        $secure = defined('SMTP_SECURE') && SMTP_SECURE ? SMTP_SECURE : ($port === 465 ? 'ssl' : 'tls');
        [$from, $fromName] = stn_mail_from();

        $transport = ($secure === 'ssl') ? "ssl://$host:$port" : "$host:$port";
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
        $fp = @stream_socket_client($transport, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new RuntimeException("SMTP connexion échouée: $errstr ($errno)");
        }
        stream_set_timeout($fp, 15);

        $read = function () use ($fp) {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') break;
            }
            return $data;
        };
        $cmd = function ($c) use ($fp, $read) {
            fwrite($fp, $c . "\r\n");
            return $read();
        };
        $expect = function ($resp, $code) {
            if (strpos($resp, (string) $code) !== 0) {
                throw new RuntimeException('SMTP: attendu ' . $code . ', reçu ' . trim($resp));
            }
        };
        $ehloHost = preg_replace('/[^A-Za-z0-9.\-]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost') ?: 'localhost';

        $expect($read(), 220);
        $cmd('EHLO ' . $ehloHost);

        if ($secure === 'tls') {
            $expect($cmd('STARTTLS'), 220);
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            if (!stream_socket_enable_crypto($fp, true, $crypto)) {
                throw new RuntimeException('SMTP STARTTLS: négociation TLS échouée');
            }
            $cmd('EHLO ' . $ehloHost);
        }

        $expect($cmd('AUTH LOGIN'), 334);
        $expect($cmd(base64_encode($user)), 334);
        $expect($cmd(base64_encode($pass)), 235);
        $expect($cmd('MAIL FROM:<' . $from . '>'), 250);
        $expect($cmd('RCPT TO:<' . $to . '>'), 250);
        $expect($cmd('DATA'), 354);

        $boundary = '=_' . bin2hex(random_bytes(8));
        $text = $text !== '' ? $text : trim(preg_replace('/\s+/', ' ', strip_tags($html)));

        $headers  = 'Date: ' . date('r') . "\r\n";
        $headers .= 'From: =?UTF-8?B?' . base64_encode($fromName) . "?= <$from>\r\n";
        $headers .= "To: <$to>\r\n";
        $headers .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $ehloHost . ">\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";

        $mime  = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text)) . "\r\n";
        $mime .= "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "\r\n--$boundary--\r\n";

        $data = $headers . "\r\n" . $mime;
        // Dot-stuffing (RFC 5321) : une ligne commençant par '.' doit être doublée
        $data = preg_replace('/^\./m', '..', $data);

        $expect($cmd($data . "\r\n."), 250);
        @fwrite($fp, "QUIT\r\n");
        @fclose($fp);
        return true;
    }
}

// ── Gabarits d'emails ────────────────────────────────────────
if (!function_exists('stn_email_layout')) {
    function stn_email_layout(string $title, string $innerHtml): string
    {
        $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        return '<!DOCTYPE html><html><body style="margin:0;background:#f4f6fb;font-family:Segoe UI,Arial,sans-serif;color:#1c2333">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb;padding:24px 0"><tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e3e8f0">'
            . '<tr><td style="background:#0d1117;padding:20px 28px"><span style="font-family:Courier New,monospace;font-size:20px;font-weight:700;color:#38bdf8">Startup<span style="color:#fff">.TN</span></span></td></tr>'
            . '<tr><td style="padding:28px">'
            . '<h1 style="margin:0 0 16px;font-size:19px;color:#0d1117">' . $t . '</h1>'
            . $innerHtml
            . '</td></tr>'
            . '<tr><td style="padding:16px 28px;background:#f4f6fb;font-size:12px;color:#6a7f9a">Startup.TN — Plateforme de veille financement pour startups tunisiennes.<br>Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet email.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }
}

if (!function_exists('stn_send_verification_code')) {
    function stn_send_verification_code(string $email, string $code): bool
    {
        $codeHtml = '<div style="font-family:Courier New,monospace;font-size:34px;font-weight:700;letter-spacing:10px;color:#0d1117;background:#eef4fb;border:1px solid #cfe2f5;border-radius:10px;text-align:center;padding:18px 0;margin:8px 0 18px">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</div>';
        $inner = '<p style="margin:0 0 8px;font-size:14px;color:#38414f">Voici votre code de vérification :</p>'
               . $codeHtml
               . '<p style="margin:0;font-size:13px;color:#6a7f9a">Ce code est valable <strong>30 minutes</strong>. Saisissez-le sur la page de vérification pour confirmer votre adresse email.</p>';
        $html = stn_email_layout('Vérification de votre adresse email', $inner);
        $text = "Votre code de vérification Startup.TN : $code\nCe code est valable 30 minutes.";
        return stn_mail($email, 'Votre code de vérification Startup.TN', $html, $text);
    }
}

if (!function_exists('stn_send_reset_link')) {
    function stn_send_reset_link(string $email, string $token): bool
    {
        $url = stn_base_url() . '/forgot.php?token=' . rawurlencode($token);
        $safe = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $inner = '<p style="margin:0 0 18px;font-size:14px;color:#38414f">Vous avez demandé la réinitialisation de votre mot de passe. Cliquez sur le bouton ci-dessous (lien valable 1 heure) :</p>'
               . '<p style="text-align:center;margin:0 0 18px"><a href="' . $safe . '" style="display:inline-block;background:#38bdf8;color:#0d1117;font-weight:700;text-decoration:none;padding:12px 26px;border-radius:8px;font-size:15px">Réinitialiser mon mot de passe</a></p>'
               . '<p style="margin:0;font-size:12px;color:#6a7f9a;word-break:break-all">Ou copiez ce lien : ' . $safe . '</p>';
        $html = stn_email_layout('Réinitialisation du mot de passe', $inner);
        $text = "Réinitialisez votre mot de passe (valable 1h) : $url";
        return stn_mail($email, 'Réinitialisation de votre mot de passe Startup.TN', $html, $text);
    }
}

if (!function_exists('stn_send_admin_new_user')) {
    function stn_send_admin_new_user(string $email, string $startupName): bool
    {
        $adminTo = defined('ADMIN_EMAIL') && ADMIN_EMAIL ? ADMIN_EMAIL : (defined('MAIL_FROM') ? MAIL_FROM : '');
        if (!$adminTo) return false;
        $inner = '<p style="margin:0 0 8px;font-size:14px;color:#38414f">Un nouveau compte a vérifié son email et attend activation :</p>'
               . '<p style="margin:0 0 4px;font-size:15px"><strong>' . htmlspecialchars($startupName, ENT_QUOTES, 'UTF-8') . '</strong></p>'
               . '<p style="margin:0 0 18px;font-size:13px;color:#6a7f9a">' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>'
               . '<p style="margin:0;font-size:13px;color:#38414f">Activez le compte depuis l\'onglet <strong>Utilisateurs</strong> de l\'administration.</p>';
        $html = stn_email_layout('Nouveau compte à activer', $inner);
        return stn_mail($adminTo, 'Startup.TN — Nouveau compte à activer : ' . $startupName, $html);
    }
}

if (!function_exists('stn_send_submission_result')) {
    function stn_send_submission_result(string $email, string $startupName, string $programName, bool $approved): bool
    {
        if ($approved) {
            $inner = '<p style="margin:0 0 12px;font-size:14px;color:#38414f">Bonne nouvelle ' . htmlspecialchars($startupName, ENT_QUOTES, 'UTF-8') . ' !</p>'
                   . '<p style="margin:0 0 12px;font-size:14px;color:#38414f">Le programme que vous avez soumis, <strong>' . htmlspecialchars($programName, ENT_QUOTES, 'UTF-8') . '</strong>, a été approuvé et publié sur la plateforme.</p>'
                   . '<p style="margin:0;font-size:13px;color:#6a7f9a">Merci pour votre contribution à la communauté.</p>';
            $subject = 'Votre soumission a été approuvée — Startup.TN';
        } else {
            $inner = '<p style="margin:0 0 12px;font-size:14px;color:#38414f">Bonjour ' . htmlspecialchars($startupName, ENT_QUOTES, 'UTF-8') . ',</p>'
                   . '<p style="margin:0 0 12px;font-size:14px;color:#38414f">Votre soumission <strong>' . htmlspecialchars($programName, ENT_QUOTES, 'UTF-8') . '</strong> n\'a pas été retenue cette fois-ci.</p>'
                   . '<p style="margin:0;font-size:13px;color:#6a7f9a">Vous pouvez proposer d\'autres programmes à tout moment.</p>';
            $subject = 'Suite à votre soumission — Startup.TN';
        }
        $html = stn_email_layout($approved ? 'Soumission approuvée' : 'Soumission examinée', $inner);
        return stn_mail($email, $subject, $html);
    }
}
