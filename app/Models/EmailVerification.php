<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\CoreUtils;
use App\MailUtils;
use App\Twig;
use Egulias\EmailValidator\Exception\AtextAfterCFWS;
use League\Uri\Components\Query;
use League\Uri\Uri;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Swift_SmtpTransport;
use Twig\Profiler\Dumper\HtmlDumper;

/**
 * @property int      $id
 * @property int      $user_id
 * @property string   $email
 * @property string   $hash
 * @property DateTime $created_at
 * @property DateTime $updated_at
 * @property User     $user       (Via relations)
 * @property DateTime $expires_at (Via magic method)
 * @method static EmailVerification find(...$args)
 * @method static EmailVerification create(...$args)
 * @method static EmailVerification find_by_hash(string $hash)
 */
class EmailVerification extends NSModel {
  public static $table_name = 'email_verifications';

  public static $belongs_to = [
    ['user'],
  ];

  public function get_expires_at():DateTime {
    $created = $this->created_at ?? new DateTime();
    return $created->add(new \DateInterval('PT2H'));
  }

  public function getVerificationPath(bool $block = false): string {
    $url = Uri::createFromString(ORIGIN.'/users/verify');
    $query = Query::createFromParams([
      'hash' => $this->hash,
      'action' => $block ? 'block' : 'verify',
    ]);
    return $url->withQuery($query);
  }

  /**
   * Checks if the verification was created within the last 2 hours
   *
   * @return bool
   */
  public function isValid():bool {
    return time() <= $this->expires_at->getTimestamp();
  }

  public function send():bool {
    if (!$this->isValid()){
      return false;
    }

    $scope = [
      'url' => ORIGIN,
      'app_name' => SITE_TITLE,
      'verify_url' => $this->getVerificationPath(),
      'block_url' => $this->getVerificationPath(true),
      'expires_at' => $this->expires_at,
      'email' => $this->email,
    ];
    $body_plain = Twig::$env->render('email/messages/verify-email.txt.twig', $scope);
    $body_html = Twig::$env->render('email/messages/verify-email.html.twig', $scope);
    return MailUtils::sendMail([$this->email], 'Verify your e-mail address', $body_plain, $body_html) !== 0;
  }
}
