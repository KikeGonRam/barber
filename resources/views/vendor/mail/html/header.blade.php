@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('images/logo-mark.png') }}" class="logo" alt="UrbanBlade" style="height: 56px; width: 56px; object-fit: contain; display: block; margin: 0 auto 12px;">
{!! $slot !!}
</a>
</td>
</tr>
