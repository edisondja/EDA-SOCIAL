@php $vf = $videoFilters ?? []; @endphp
@if(!empty($vf['q']))
    <input type="hidden" name="_filter_q" value="{{ $vf['q'] }}">
@endif
@if(!empty($vf['mod']))
    <input type="hidden" name="_filter_moderation" value="{{ $vf['mod'] }}">
@endif
@if(array_key_exists('pub', $vf) && ($vf['pub'] ?? '') !== '')
    <input type="hidden" name="_filter_published" value="{{ $vf['pub'] }}">
@endif
