<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérifiez votre adresse email</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: #ffffff;
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .content {
            padding: 40px 30px;
            color: #333333;
            line-height: 1.6;
        }
        .content p {
            margin: 0 0 20px;
            font-size: 16px;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .verify-button {
            display: inline-block;
            padding: 15px 40px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
        }
        .alternative-link {
            margin-top: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 6px;
            word-break: break-all;
        }
        .alternative-link p {
            margin: 0 0 10px;
            font-size: 14px;
            color: #666;
        }
        .alternative-link a {
            color: #28a745;
            text-decoration: none;
            font-size: 12px;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 30px;
            text-align: center;
            color: #666666;
            font-size: 14px;
            border-top: 1px solid #e9ecef;
        }
        .footer p {
            margin: 5px 0;
        }
        .info-box {
            background-color: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box p {
            margin: 0;
            color: #014361;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>✉️ Vérifiez votre adresse email</h1>
        </div>

        <div class="content">
            <p>Bonjour <strong>{{ $user->name }}</strong>,</p>

            <p>Bienvenue sur <strong>E-Learning Platform</strong> ! 🎉</p>

            <p>Pour activer votre compte et commencer votre parcours d'apprentissage, veuillez vérifier votre adresse email en cliquant sur le bouton ci-dessous :</p>

            <div class="button-container">
                <a href="{{ $verificationLink }}" class="verify-button">
                    Vérifier mon email
                </a>
            </div>

            <div class="info-box">
                <p><strong>⏱️ Important :</strong> Ce lien est valable pendant <strong>24 heures</strong> seulement.</p>
            </div>

            <div class="alternative-link">
                <p><strong>Le bouton ne fonctionne pas ?</strong></p>
                <p>Copiez et collez ce lien dans votre navigateur :</p>
                <a href="{{ $verificationLink }}">{{ $verificationLink }}</a>
            </div>

            <p style="margin-top: 30px;">À très bientôt,<br>
            <strong>L'équipe E-Learning Platform</strong></p>
        </div>

        <div class="footer">
            <p>© {{ date('Y') }} E-Learning Platform. Tous droits réservés.</p>
            <p>Cet email a été envoyé à <strong>{{ $user->email }}</strong></p>
            <p style="margin-top: 15px; font-size: 12px; color: #999;">
                Si vous n'avez pas créé de compte, ignorez cet email.
            </p>
        </div>
    </div>
</body>
</html>