<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TestEmailCommand extends Command
{
    protected $signature = 'email:test {email}';
    protected $description = 'Tester la configuration email';

    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info('📧 Test d\'envoi d\'email vers: ' . $email);
        
        try {
            Mail::raw('Ceci est un email de test depuis E-Learning Platform.

Si vous recevez cet email, votre configuration mail fonctionne correctement! ✅

Vous pouvez maintenant tester la fonctionnalité "Mot de passe oublié".', function ($message) use ($email) {
                $message->to($email)
                        ->subject('Test Email - E-Learning Platform');
            });
            
            $this->info('✅ Email envoyé avec succès!');
            $this->info('📬 Vérifiez votre boîte de réception (et spam)');
            
            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Erreur lors de l\'envoi: ' . $e->getMessage());
            Log::error('Test email failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            
            return 1;
        }
    }
}
