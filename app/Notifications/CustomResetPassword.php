<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class CustomResetPassword extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        // URL de ta page Angular "nouveau mot de passe"
        $baseUrl = config('app.frontend_newpassword_url', env('FRONTEND_NEWPASSWORD_URL', 'http://localhost:4200/auth/newpassword'));
        $url     = $baseUrl.'?token='.$this->token.'&email='.urlencode($notifiable->getEmailForPasswordReset());

        // Durée d’expiration (minutes) lue depuis la config
        $expire  = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        // Variables passées à la vue Blade
        $data = [
            'appName'   => config('app.name', 'EAU-LA-MAMAN'),
            'url'       => $url,
            'expiresIn' => $expire.' minutes',
            'user'      => $notifiable,
            // optionnels si tu veux forcer un nom
            'userFirstName' => $notifiable->prenom ?? $notifiable->first_name ?? null,
            'userLastName'  => $notifiable->nom ?? $notifiable->last_name ?? null,
        ];

        return (new MailMessage)
            ->subject('Réinitialisation du mot de passe — '.$data['appName'])
            ->view('emails.passwordReset', $data);
            // si tu veux aussi une version texte :
            // ->view(['emails.passwordReset', 'emails.passwordReset_plain'], $data);
    }
}
