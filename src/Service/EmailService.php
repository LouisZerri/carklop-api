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
                    <p>© Carklop - Covoiturage transfrontalier</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    public function sendPasswordResetEmail(User $user): void
    {
        $resetUrl = $this->appUrl . '/reset-password/' . $user->getResetPasswordToken();

        $email = (new Email())
            ->from('noreply@carklop.fr')
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe CarKlop')
            ->html($this->renderResetEmail($user, $resetUrl));

        $this->mailer->send($email);
    }

    private function renderResetEmail(User $user, string $resetUrl): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Réinitialisation mot de passe</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h1 style="color: #4F46E5;">Réinitialisation de mot de passe</h1>
                <p>Bonjour {$user->getFirstName()},</p>
                <p>Vous avez demandé à réinitialiser votre mot de passe Carklop.</p>
                <p>Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe :</p>
                <p style="text-align: center; margin: 30px 0;">
                    <a href="{$resetUrl}" 
                    style="background-color: #4F46E5; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">
                        Réinitialiser mon mot de passe
                    </a>
                </p>
                <p style="color: #666; font-size: 14px;">
                    Ou copiez ce lien dans votre navigateur :<br>
                    <a href="{$resetUrl}">{$resetUrl}</a>
                </p>
                <p style="color: #666; font-size: 14px;">Ce lien expire dans 1 heure.</p>
                <p style="color: #999; font-size: 12px;">Si vous n'avez pas demandé cette réinitialisation, ignorez cet email.</p>
                <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
                <p style="color: #999; font-size: 12px; text-align: center;">
                    © Carklop - Covoiturage transfrontalier
                </p>
            </div>
        </body>
        </html>
        HTML;
    }
}
