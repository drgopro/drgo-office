@include('partials.tag-picker-assets')
<div class="crm-tagpick" data-key="{{ $key }}" data-major='@json($preMajor ?? [])' data-minor='@json($preMinor ?? [])'></div>
<script>CrmTagPicker.init(@json($key));</script>
