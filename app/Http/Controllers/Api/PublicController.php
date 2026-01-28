<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PublicController extends Controller
{
    /**
     * Soumettre un formulaire de contact
     */
    public function submitContact(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'subject' => 'required|string|max:255',
                'message' => 'required|string|max:5000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $submission = ContactSubmission::create([
                'name' => $request->name,
                'email' => $request->email,
                'subject' => $request->subject,
                'message' => $request->message,
                'status' => 'new',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            Log::info('📨 Nouveau message de contact reçu', [
                'id' => $submission->id,
                'email' => $submission->email,
                'subject' => $submission->subject,
            ]);

            // TODO: Envoyer email de notification à l'admin
            // Mail::to(config('mail.admin_email'))->send(new ContactSubmissionNotification($submission));

            return response()->json([
                'success' => true,
                'message' => 'Votre message a été envoyé avec succès. Nous vous répondrons sous 24h.',
            ], 201);

        } catch (\Exception $e) {
            Log::error('❌ Erreur soumission contact: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du message',
            ], 500);
        }
    }

    /**
     * S'inscrire à la newsletter
     */
    public function subscribeNewsletter(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email invalide',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Vérifier si déjà inscrit
            $existing = NewsletterSubscriber::where('email', $request->email)->first();

            if ($existing) {
                if ($existing->is_active) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cet email est déjà inscrit à la newsletter',
                    ], 409);
                } else {
                    // Réactiver l'abonnement
                    $existing->update([
                        'is_active' => true,
                        'subscribed_at' => now(),
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Votre abonnement a été réactivé avec succès !',
                    ], 200);
                }
            }

            $subscriber = NewsletterSubscriber::create([
                'email' => $request->email,
                'is_active' => true,
                'subscribed_at' => now(),
                'ip_address' => $request->ip(),
            ]);

            Log::info('📧 Nouveau abonné newsletter', [
                'email' => $subscriber->email,
            ]);

            // TODO: Envoyer email de confirmation
            // Mail::to($subscriber->email)->send(new WelcomeNewsletterMail());

            return response()->json([
                'success' => true,
                'message' => 'Merci ! Vous êtes maintenant inscrit à notre newsletter.',
            ], 201);

        } catch (\Exception $e) {
            Log::error('❌ Erreur inscription newsletter: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription',
            ], 500);
        }
    }

    /**
     * Se désabonner de la newsletter
     */
    public function unsubscribeNewsletter(Request $request)
    {
        try {
            $email = $request->input('email');

            $subscriber = NewsletterSubscriber::where('email', $email)->first();

            if (!$subscriber) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email non trouvé',
                ], 404);
            }

            $subscriber->update([
                'is_active' => false,
                'unsubscribed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Vous avez été désinscrit de la newsletter',
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erreur désinscription newsletter: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la désinscription',
            ], 500);
        }
    }
}