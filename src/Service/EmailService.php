<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private string $appUrl
    ) {}

    public function sendVerificationEmail(User $user): void
    {
        $token = $user->getEmailVerificationToken();
        $verificationUrl = $this->appUrl . '/api/verify-email/' . $token;

        $email = (new Email())
            ->from('noreply@carklop.fr')
            ->to($user->getEmail())
            ->subject('Carklop - Vérifiez votre email')
            ->html($this->getVerificationTemplate($user, $verificationUrl));

        $this->mailer->send($email);
    }

    private function getVerificationTemplate(User $user, string $url): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #FF6B35; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
                .button { display: inline-block; background: #FF6B35; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 20px; color: #888; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Carklop</h1>
                </div>
                <div class="content">
                    <h2>Bonjour {$user->getFirstName()} !</h2>
                    <p>Merci de vous être inscrit sur CarKlop. Pour activer votre compte et commencer à utiliser nos services, veuillez vérifier votre adresse email.</p>
                    <p style="text-align: center;">
                        <a href="{$url}" class="button">Vérifier mon email</a>
                    </p>
                    <p>Ou copiez ce lien dans votre navigateur :</p>
                    <p style="word-break: break-all; color: #666;">{$url}</p>
                    <p>Ce lien expire dans 24 heures.</p>
                </div>
                <div class="footer">
                    <p>© CarKlop - Covoiturage transfrontalier</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }
}