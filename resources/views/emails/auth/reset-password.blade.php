<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Réinitialisez votre mot de passe – Statsio</title>
  <!--[if mso]>
  <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
  <![endif]-->
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:'Manrope','Helvetica Neue',Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;">
    <tr>
      <td style="padding:48px 16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;">

          {{-- Header --}}
          <tr>
            <td style="background:linear-gradient(135deg,#7c3aed 0%,#8b5cf6 50%,#a78bfa 100%);border-radius:20px 20px 0 0;padding:32px 40px;text-align:center;">
              <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto;">
                <tr>
                  <td>
                    <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background-color:rgba(255,255,255,0.6);margin-right:10px;vertical-align:middle;"></span>
                    <span style="font-size:20px;font-weight:800;color:#ffffff;letter-spacing:-0.5px;vertical-align:middle;">Statsio</span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          {{-- Body --}}
          <tr>
            <td style="background:#ffffff;padding:40px;border-radius:0 0 20px 20px;box-shadow:0 4px 24px rgba(15,23,42,0.08);">

              {{-- Greeting --}}
              <h1 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#0f172a;letter-spacing:-0.3px;">
                Réinitialisez votre mot de passe
              </h1>
              <p style="margin:0 0 28px;font-size:15px;line-height:1.7;color:#64748b;">
                @if($firstName)
                  Bonjour {{ $firstName }},<br>
                @endif
                Vous avez demandé la réinitialisation de votre mot de passe sur Statsio. Cliquez sur le bouton ci-dessous pour en choisir un nouveau. Ce lien est valable <strong style="color:#475569;">60 minutes</strong>.
              </p>

              {{-- CTA button --}}
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
                <tr>
                  <td style="background:linear-gradient(135deg,#f5f0ff 0%,#ede9fe 100%);border:1px solid #ddd6fe;border-radius:16px;padding:28px 24px;text-align:center;">
                    <a href="{{ $resetUrl }}" style="display:inline-block;background:linear-gradient(135deg,#7c3aed 0%,#8b5cf6 100%);color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;padding:14px 32px;border-radius:12px;">
                      Réinitialiser mon mot de passe
                    </a>
                    <p style="margin:16px 0 0;font-size:12px;color:#7c3aed;word-break:break-all;">
                      {{ $resetUrl }}
                    </p>
                  </td>
                </tr>
              </table>

              {{-- Warning --}}
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:32px;">
                <tr>
                  <td style="background:#fafafa;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;">
                    <p style="margin:0;font-size:13px;color:#64748b;line-height:1.6;">
                      🔒 &nbsp;Ce lien expire dans 60 minutes. Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail : votre mot de passe restera inchangé.
                    </p>
                  </td>
                </tr>
              </table>

              {{-- Divider --}}
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                <tr>
                  <td style="border-top:1px solid #e2e8f0;font-size:0;line-height:0;">&nbsp;</td>
                </tr>
              </table>

              {{-- Footer --}}
              <p style="margin:0;font-size:12px;color:#94a3b8;text-align:center;line-height:1.7;">
                Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet e-mail.<br>
                &copy; {{ date('Y') }} Statsio &mdash; Tous droits réservés.
              </p>

            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>

</body>
</html>
