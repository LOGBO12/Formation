<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Créer une notification
     */
    public function creer($userId, $type, $titre, $message, $lien = null, $data = [])
    {
        try {
            return Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'titre' => $titre,
                'message' => $message,
                'lien' => $lien,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur création notification: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Notifier une nouvelle formation à tous les apprenants
     */
    public function notifierNouvelleFormation($formation)
    {
        try {
            $apprenants = User::where('role', 'apprenant')->get();
            
            foreach ($apprenants as $apprenant) {
                $this->creer(
                    $apprenant->id,
                    'nouvelle_formation',
                    'Nouvelle formation disponible',
                    "La formation \"{$formation->titre}\" vient d'être publiée dans le domaine {$formation->domaine->nom}",
                    "/apprenant/formations/{$formation->id}",
                    [
                        'formation_id' => $formation->id,
                        'formation_titre' => $formation->titre,
                        'formateur_id' => $formation->formateur_id,
                        'formateur_nom' => $formation->formateur->name,
                    ]
                );
            }

            Log::info("🔔 Notifications envoyées pour la formation #{$formation->id} à {$apprenants->count()} apprenants");
            
        } catch (\Exception $e) {
            Log::error('Erreur notifierNouvelleFormation: ' . $e->getMessage());
        }
    }

    /**
     * Notifier un nouveau message dans une communauté
     */
    public function notifierNouveauMessage($message, $communaute)
    {
        try {
            // Récupérer tous les membres sauf l'auteur
            $membres = $communaute->membres()
                ->where('user_id', '!=', $message->user_id)
                ->get();
            
            foreach ($membres as $membre) {
                $this->creer(
                    $membre->id,
                    'nouveau_message',
                    "Nouveau message dans {$communaute->nom}",
                    "{$message->user->name} a posté un message : " . \Illuminate\Support\Str::limit($message->contenu, 100),
                    "/apprenant/communautes/{$communaute->id}",
                    [
                        'message_id' => $message->id,
                        'communaute_id' => $communaute->id,
                        'communaute_nom' => $communaute->nom,
                        'auteur_id' => $message->user_id,
                        'auteur_nom' => $message->user->name,
                    ]
                );
            }

            Log::info("🔔 Notifications envoyées pour le message #{$message->id} à {$membres->count()} membres");
            
        } catch (\Exception $e) {
            Log::error('Erreur notifierNouveauMessage: ' . $e->getMessage());
        }
    }

    /**
     * Notifier le formateur d'un nouveau paiement
     */
    public function notifierPaiementRecu($paiement)
    {
        try {
            $formation = $paiement->formation;
            $formateur = $formation->formateur;

            $this->creer(
                $formateur->id,
                'paiement_recu',
                'Nouveau paiement reçu',
                "{$paiement->user->name} s'est inscrit à votre formation \"{$formation->titre}\" pour {$paiement->montant} FCFA",
                "/formateur/formations/{$formation->id}",
                [
                    'paiement_id' => $paiement->id,
                    'formation_id' => $formation->id,
                    'apprenant_id' => $paiement->user_id,
                    'montant' => $paiement->montant,
                ]
            );

            Log::info("🔔 Notification paiement envoyée au formateur #{$formateur->id}");
            
        } catch (\Exception $e) {
            Log::error('Erreur notifierPaiementRecu: ' . $e->getMessage());
        }
    }

    /**
     * Notifier l'apprenant que son inscription est validée
     */
    public function notifierInscriptionValidee($inscription)
    {
        try {
            $this->creer(
                $inscription->user_id,
                'inscription_validee',
                'Inscription validée',
                "Votre inscription à la formation \"{$inscription->formation->titre}\" a été validée. Vous pouvez maintenant accéder au contenu.",
                "/apprenant/formations/{$inscription->formation_id}",
                [
                    'inscription_id' => $inscription->id,
                    'formation_id' => $inscription->formation_id,
                    'formation_titre' => $inscription->formation->titre,
                ]
            );

            Log::info("🔔 Notification inscription validée envoyée à l'apprenant #{$inscription->user_id}");
            
        } catch (\Exception $e) {
            Log::error('Erreur notifierInscriptionValidee: ' . $e->getMessage());
        }
    }

    /**
     * Notifier un nouveau cours ajouté à une formation
     */
    public function notifierNouveauCours($cours)
    {
        try {
            $formation = $cours->formation;
            
            // Notifier tous les apprenants inscrits
            $inscrits = $formation->inscriptions()
                ->where('statut', 'active')
                ->get();
            
            foreach ($inscrits as $inscription) {
                $this->creer(
                    $inscription->user_id,
                    'nouveau_cours',
                    "Nouveau cours ajouté",
                    "Un nouveau cours \"{$cours->titre}\" a été ajouté à la formation \"{$formation->titre}\"",
                    "/apprenant/formations/{$formation->id}/cours/{$cours->id}",
                    [
                        'cours_id' => $cours->id,
                        'cours_titre' => $cours->titre,
                        'formation_id' => $formation->id,
                        'formation_titre' => $formation->titre,
                    ]
                );
            }

            Log::info("🔔 Notifications nouveau cours envoyées à {$inscrits->count()} apprenants");
            
        } catch (\Exception $e) {
            Log::error('Erreur notifierNouveauCours: ' . $e->getMessage());
        }
    }

    /**
     * Notifier un certificat obtenu
     */
    public function notifierCertificatObtenu($certificat)
    {
        try {
            $this->creer(
                $certificat->user_id,
                'certificat_obtenu',
                '🎉 Certificat obtenu !',
                "Félicitations ! Vous avez obtenu le certificat pour la formation \"{$certificat->formation->titre}\"",
                "/apprenant/certificats/{$certificat->id}",
                [
                    'certificat_id' => $certificat->id,
                    'formation_id' => $certificat->formation_id,
                    'formation_titre' => $certificat->formation->titre,
                ]
            );

            Log::info("🔔 Notification certificat envoyée à l'apprenant #{$certificat->user_id}");
            
        } catch (\Exception $e) {
            Log::error('Erreur notifierCertificatObtenu: ' . $e->getMessage());
        }
    }

    /**
     * Récupérer les notifications d'un utilisateur
     */
    public function getNotifications($userId, $limit = 20)
    {
        return Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Compter les notifications non lues
     */
    public function compterNonLues($userId)
    {
        return Notification::where('user_id', $userId)
            ->where('lu', false)
            ->count();
    }

    /**
     * Marquer toutes les notifications comme lues
     */
    public function marquerToutCommeLu($userId)
    {
        return Notification::where('user_id', $userId)
            ->where('lu', false)
            ->update([
                'lu' => true,
                'lu_at' => now(),
            ]);
    }

    /**
     * Supprimer les anciennes notifications (> 30 jours)
     */
    public function nettoyerAnciennesNotifications()
    {
        try {
            $count = Notification::where('created_at', '<', now()->subDays(30))
                ->where('lu', true)
                ->delete();

            Log::info("🧹 {$count} anciennes notifications supprimées");
            return $count;
            
        } catch (\Exception $e) {
            Log::error('Erreur nettoyage notifications: ' . $e->getMessage());
            return 0;
        }
    }
}