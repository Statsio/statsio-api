<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>Invitation à rejoindre {{ $channelName }} sur Statsio</title>
<!--[if !mso]><!--><link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet"><!--<![endif]-->
<style>
  @media only screen and (max-width:620px) {
    .sio-pad { padding-left:24px !important; padding-right:24px !important; }
    .sio-h1 { font-size:24px !important; line-height:32px !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background-color:#f4f2f8;">
<span style="display:none;font-size:1px;color:#f4f2f8;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">{{ $inviterName }} vous invite à rejoindre {{ $channelName }} en tant que {{ $roleLabel }}.</span>

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
            <td bgcolor="#f2ecfd" style="background-color:#f2ecfd;border-radius:6px;padding:6px 11px;font-family:'JetBrains Mono','Courier New',Courier,monospace;font-size:11px;line-height:14px;mso-line-height-rule:exactly;font-weight:bold;letter-spacing:1px;color:#6d3fd4;text-transform:uppercase;">Invitation d'équipe</td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td class="sio-pad" style="padding:18px 40px 0 40px;">
        <h1 class="sio-h1" style="margin:0;font-family:'Manrope',Helvetica,Arial,sans-serif;font-size:28px;line-height:36px;mso-line-height-rule:exactly;font-weight:bold;color:#18181f;letter-spacing:-0.6px;">{{ $inviterName }} vous invite à rejoindre {{ $channelName }}.</h1>
      </td>
    </tr>
    <tr>
      <td class="sio-pad" style="padding:14px 40px 0 40px;font-family:'Manrope',Helvetica,Arial,sans-serif;font-size:15px;line-height:24px;mso-line-height-rule:exactly;color:#55555f;">
        Vous êtes invité·e en tant que <strong style="color:#18181f;">{{ $roleLabel }}</strong>. Ce lien expire dans <strong style="color:#18181f;">7 jours</strong>.
      </td>
    </tr>

    {{-- CTA --}}
    <tr>
      <td class="sio-pad" style="padding:26px 40px 0 40px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td bgcolor="#8b5cf6" align="center" style="background-color:#8b5cf6;border-radius:10px;">
              <a href="{{ $acceptUrl }}" style="display:block;padding:14px 28px;font-family:'Manrope',Helvetica,Arial,sans-serif;font-size:15px;line-height:20px;mso-line-height-rule:exactly;font-weight:bold;color:#ffffff;text-decoration:none;">Accepter l'invitation</a>
            </td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td class="sio-pad" style="padding:12px 40px 32px 40px;font-family:'Manrope',Helvetica,Arial,sans-serif;font-size:12.5px;line-height:20px;mso-line-height-rule:exactly;color:#8b8b96;">
        Si le bouton ne fonctionne pas, copiez ce lien&nbsp;: <a href="{{ $acceptUrl }}" style="color:#6d3fd4;text-decoration:underline;">{{ $acceptUrl }}</a>
      </td>
    </tr>

    <tr><td height="12" style="height:12px;line-height:12px;font-size:1px;">&nbsp;</td></tr>
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
        Si vous ne vous attendiez pas à cette invitation, vous pouvez ignorer cet e-mail.
      </td>
    </tr>
  </table>

</td>
</tr>
</table>
</body>
</html>
