@extends('layouts.app')

@section('content')

    @if($enable_auth_admin_controls)
    <div class="settings-tabs" role="tablist">
        @foreach ($groups as $index => $group)
        <button type="button"
                role="tab"
                class="settings-tab{{ $index === 0 ? ' active' : '' }}"
                data-tab="group-{{ $group->id }}">
            {{ __($group->title) }}
        </button>
        @endforeach
    </div>

    @foreach ($groups as $index => $group)
    <div class="settings-panel{{ $index === 0 ? ' active' : '' }}" id="group-{{ $group->id }}">
        <section class="module-container">
            <header>
                <div class="section-title">
                    {{ __($group->title) }}
                </div>
                @if($index === 0)
                <div class="module-actions">
                    <a href="{{ route('items.import', []) }}" id="item-import" class="button"><i class="fas fa-file-arrow-up"></i><span>{{ __('import') }}</span></a>
                    <a href="#export" id="item-export" class="button"><i class="fas fa-file-arrow-down"></i><span>{{ __('export') }}</span></a>
                </div>
                @endif
            </header>

            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('app.settings.label') }}</th>
                        <th style="width: 60%;">{{ __('app.settings.value') }}</th>
                        <th class="text-center" style="width: 75px;">{{ __('app.settings.edit') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @if (count($group->settings) > 0)
                        @foreach ($group->settings as $setting)
                            <tr class="setting-row" data-setting-id="{{ $setting->id }}">
                                <td>{{ __($setting->label) }}</td>
                                <td class="setting-value-cell">
                                    @if($setting->type === "textarea")
                                    <pre class="setting-value">{!! $setting->list_value !!}</pre>
                                    @else
                                    <span class="setting-value">{!! $setting->list_value !!}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if(\App\Setting::isEditable($setting))
                                    <button type="button"
                                            class="setting-edit-btn"
                                            data-id="{{ $setting->id }}"
                                            title="{{ __('app.settings.edit') }} {{ $setting->label }}">
                                        <i class="fa fa-pencil"></i>
                                    </button>
                                    @endif
                                </td>
                            </tr>
                            @if(\App\Setting::isEditable($setting))
                            <tr class="setting-editor-row" data-for="{{ $setting->id }}" hidden>
                                <td colspan="3">
                                    <form class="setting-inline-form"
                                          method="POST"
                                          action="{{ route('settings.edit', $setting->id) }}"
                                          enctype="multipart/form-data">
                                        @csrf
                                        @method('PATCH')
                                        <div class="setting-inline-label">{{ __($setting->label) }}</div>
                                        <div class="setting-inline-body">
                                            {!! $setting->edit_value !!}
                                        </div>
                                        <div class="setting-inline-actions">
                                            <button type="submit" class="button"><i class="fa fa-save"></i><span>{{ __('app.buttons.save') }}</span></button>
                                            <button type="button" class="button setting-cancel"><i class="fa fa-ban"></i><span>{{ __('app.buttons.cancel') }}</span></button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            @endif
                        @endforeach
                    @else

                        <tr>
                            <td colspan="3" class="form-error text-center">
                                <strong>{{ __('app.settings.no_items') }}</strong>
                            </td>
                        </tr>
                    @endif

                
                </tbody>
            </table>
        </section>
    </div>
    @endforeach
    @else
    <section class="module-container">
        <header>
            <div class="section-title">
                {{ __('app.unauthorized_for_form') }}
            </div>
        </header>
    </section>
    @endif

@endsection
