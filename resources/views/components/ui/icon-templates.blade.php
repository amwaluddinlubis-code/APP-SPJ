<div class="hidden" aria-hidden="true" data-ui-icon-templates>
    @foreach (['save','edit','trash','plus','download','printer','arrow-left','arrow-right','refresh','arrow-up','arrow-down','eye','document','check','close','external-link','filter'] as $iconName)
        <template data-ui-icon-template="{{ $iconName }}">
            <x-ui.icon :name="$iconName" size="sm" />
        </template>
    @endforeach
</div>
