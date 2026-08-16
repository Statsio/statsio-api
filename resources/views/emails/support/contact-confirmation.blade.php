<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>Votre demande a bien été reçue — Statsio</title>
<!--[if !mso]><!--><link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet"><!--<![endif]-->
<style>
  @media only screen and (max-width:620px) {
    .sio-pad { padding-left:24px !important; padding-right:24px !important; }
    .sio-h1 { font-size:24px !important; line-height:32px !important; }
    .sio-stack { display:block !important; width:100% !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background-color:#f4f2f8;">
<span style="display:none;font-size:1px;color:#f4f2f8;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">Votre demande a bien été enregistrée. Notre équipe vous répond sous 24 h ouvrées. Référence #{{ $reference }}.</span>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f4f2f8;">
<tr>
<td align="center" style="padding:32px 12px;">

  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:600px;max-width:600px;background-color:#ffffff;border-radius:16px;border:1px solid #e6e1f0;">

    {{-- Logo --}}
    <tr>
      <td class="sio-pad" style="padding:32px 40px 0 40px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td width="40" height="40" valign="middle" style="width:40px;height:40px;line-height:0;"><img src="{{ $logoUrl }}" width="40" height="40" alt="Statsio" style="display:block;width:40px;height:40px;border:0;outline:none;text-decoration:none;"></td>
            <td style="padding-left:12px;font-family:'Manrope',Helvetica,Arial,sans-serif;font-size:19px;line-height:24px;mso-line-height-rule:exactly;font-weight:bold;color:#18181f;letter-spacing:-0.3px;">Statsio</td>
          </tr>
        </table>
      </td>
    </tr>

    {{-- Titre --}}
    <tr>
      <td class="sio-pad" style="padding:28px 40px 0 40px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td bgcolor="#ede8fb" style="background-color:#ede8fb;border-radius:6px;padding:6px 11px;font-family:'JetBrains Mono','Courier New',Courier,monospace;font-size:11px;line-height:14px;mso-line-height-rule:exactly;font-weight:bold;letter-spacing:1px;color:#6d3fd4;text-transform:uppercase;">Demande reçue</td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td class="sio-pad" style="padding:18px 40px 0 40px;">
        <h1 class="sio-h1" style="margin:0;font-family:'Manrope',Helvetica,Arial,sans-serif;font-size:28px;line-height:36px;mso-line-height-rule:exactly;font-weight:bold;color:#18181f;letter-spacing:-0.6px;">Merci {{ $firstName }}, nous avons bien reçu votre message.</h1>
      </td>
    </tr>
    <tr>
      <td class="sio-pad" style="padding:14px 40px 0 40px;font-family:'Manrope',Helvetica,Arial,sans-serif;font-size:15px;line-height:24px;mso-line-height-rule:exactly;color:#55555f;">
        Votre demande a été transmise à l'équipe support Statsio. Un conseiller vous répondra par e-mail sous <strong style="color:#18181f;">24 heures ouvrées</strong>. Vous n'avez rien d'autre à faire pour le moment.
      </td>
    </tr>

    {{-- Récapitulatif --}}
    <tr>
      <td class="sio-pad" style="padding:26px 40px 0 40px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background-color:#faf9fd;border:1px solid #eae5f4;border-radius:12px;">
          <tr>
            <td style="padding:20px 22px 6px 22px;font-family:'JetBrains Mono','Courier New',Courier,monospace;font-size:11px;line-height:14px;mso-line-height-rule:exactly;font-weight:bold;letter-spacing:1px;color:#8b8b96;text-transform:uppercase;">Récapitulatif</td>
          </tr>
          <tr>
            <td style="padding:0 22px 20px 22px;">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;">
                <tr>
                  <td width="130" style="width:130px;padding:8px 0;font-family:'Manrope',Helvetica,Arial,sans-serif;font-size:13px;line-height:20px;mso-line-height-rule:exactly;color:#8b8b96;">Référence</td>
                  <td style="padding:8px 0;font-family:'JetBrains Mono','Courier New',Courier,monospace;font-size:13px;line-height:20px;mso-line-height-rule:exactly;font-weight:bold;color:#18181f;">#{{ $reference }}</td>
                </tr>
                <tr>
                  <td width="130" style="width:130px;padding:8px 0;border-top:1px solid #eae5f4;font-family:'Manrope',Helvetica,Arial,sans-serif;font-size:13px;line-height:20px;mso-line-height-rule:exactly;color:#8b8b96;">Sujet</td>
                  <td style="padding:8px 0;border-top:1px solid #eae5f4;font-family:'Manrope',Helvetica,Arial,sans-serif;font-size:13px;line-height:20px;mso-line-height-rule:exactly;color:#18181f;">{{ $reasonLabel }}</td>
                </tr>
                <tr>
                  <td width="130" style="width:130px;padding:8px 0;border-top:1px solid #eae5f4;font-family:'Manrope',Helvetica,Arial,sans-serif;font-size:13px;line-height:20px;mso-line-height-rule:exactly;color:#8b8b96;">Envoyée le</td>
                  <td style="padding:8px 0;border-top:1px solid #eae5f4;font-family:'Manrope',Helvetica,Arial,sans-serif;font-size:13px;line-height:20px;mso-line-height-rule:exactly;color:#18181f;">{{ $sentAt }}</td>
                </tr>
                <tr>
                  <td width="130" style="width:130px;padding:8px 0;border-top:1px solid #eae5f4;font-family:'Manrope',Helvetica,Arial,sans-serif;font-size:13px;line-height:20px;mso-line-height-rule:exactly;color:#8b8b96;">Message</td>
                  <td style="padding:8px 0;border-top:1px solid #eae5f4;font-family:'Manrope',Helvetica,Arial,sans-serif;font-size:13px;line-height:20px;mso-line-height-rule:exactly;color:#55555f;">« {{ $messageExcerpt }} »</td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    {{-- CTA --}}
    <tr>
      <td class="sio-pad" style="padding:26px 40px 0 40px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td bgcolor="#8b5cf6" align="center" style="background-color:#8b5cf6;border-radius:10px;">
              <a href="{{ $trackUrl }}" style="display:block;padding:14px 28px;font-family:'Manrope',Helvetica,Arial,sans-serif;font-size:15px;line-height:20px;mso-line-height-rule:exactly;font-weight:bold;color:#ffffff;text-decoration:none;">Suivre ma demande</a>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    {{-- En attendant --}}
    <tr>
      <td class="sio-pad" style="padding:30px 40px 0 40px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;border-top:1px solid #eae5f4;">
          <tr><td height="24" style="height:24px;line-height:24px;font-size:1px;">&nbsp;</td></tr>
          <tr>
            <td style="font-family:'Manrope',Helvetica,Arial,sans-serif;font-size:15px;line-height:22px;mso-line-height-rule:exactly;font-weight:bold;color:#18181f;">En attendant notre réponse</td>
          </tr>
          <tr><td height="14" style="height:14px;line-height:14px;font-size:1px;">&nbsp;</td></tr>
          <tr>
            <td style="font-family:'Manrope',Helvetica,Arial,sans-serif;font-size:14px;line-height:22px;mso-line-height-rule:exactly;color:#55555f;">
              &rarr;&nbsp; <a href="{{ $helpUrl }}" style="color:#6d3fd4;text-decoration:none;font-weight:bold;">Nous contacter</a> — une autre question&nbsp;?<br>
              &rarr;&nbsp; <a href="{{ $channelsUrl }}" style="color:#6d3fd4;text-decoration:none;font-weight:bold;">Chaînes</a> — explorez nos chaînes éditoriales<br>
              &rarr;&nbsp; <a href="{{ $tvUrl }}" style="color:#6d3fd4;text-decoration:none;font-weight:bold;">Programme TV</a> — audiences du jour
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <tr><td height="36" style="height:36px;line-height:36px;font-size:1px;">&nbsp;</td></tr>
  </table>

  {{-- Pied de page --}}
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:600px;max-width:600px;">
    <tr>
      <td class="sio-pad" align="center" style="padding:24px 40px 8px 40px;font-family:'Manrope',Helvetica,Arial,sans-serif;font-size:12px;line-height:20px;mso-line-height-rule:exactly;color:#8b8b96;">
        Statsio — Données d'audience TV, santé et opinion
      </td>
    </tr>
    <tr>
      <td align="center" style="padding:0 40px 32px 40px;font-family:'Manrope',Helvetica,Arial,sans-serif;font-size:12px;line-height:20px;mso-line-height-rule:exactly;color:#8b8b96;">
        Vous recevez cet e-mail suite à votre demande de contact.
      </td>
    </tr>
  </table>

</td>
</tr>
</table>
</body>
</html>
