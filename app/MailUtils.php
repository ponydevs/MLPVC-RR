<?php

namespace App;

use Swift_Mailer;
use Swift_Message;
use Swift_SmtpTransport;

class MailUtils {
  public static function createMailer():Swift_Mailer {

    [$mail_user, $mail_pass] = [CoreUtils::env('MAIL_USERNAME'), CoreUtils::env('MAIL_PASSWORD')];
    $transport = (new Swift_SmtpTransport(CoreUtils::env('MAIL_HOST'), CoreUtils::env('MAIL_PORT')));
    if ($mail_user !== null){
      $transport->setUsername($mail_user);
    }
    if ($mail_pass !== null){
      $transport->setPassword($mail_pass);
    }

    return new Swift_Mailer($transport);
  }

  public static function sendMail(array $to, string $subject, string $body_plain, string $body_html):int {
    /** @var Swift_Mailer $mailer */
    static $mailer = null;
    if ($mailer === null) {
      $mailer = self::createMailer();
    }

    $message = (new Swift_Message($subject))
      ->setFrom(CoreUtils::env('MAIL_FROM'), 'Penny Curve')
      ->setTo($to)
      ->setBody($body_html, 'text/html')
      ->addPart($body_plain, 'text/plain');

    return $mailer->send($message);
  }
}
