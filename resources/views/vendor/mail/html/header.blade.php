@props(['url'])
<tr>
<td class="header" align="center">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<img src="cid:{{ \App\Services\Mail\ShopBranding::LOGO_CID }}" class="logo" width="84" height="84" alt="UrbanBlade" style="width: 84px; height: 84px; display: block; margin: 0 auto 14px; border: 0; border-radius: 20px;">
{!! $slot !!}
</a>
</td>
</tr>
