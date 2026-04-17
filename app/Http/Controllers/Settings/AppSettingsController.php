<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AppSettingsUpdateRequest;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Storage;

class AppSettingsController extends Controller
{
    /**
     * Show the application settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/App', [
            'settings' => [
                'google_sheet_url' => AppSetting::getValue(AppSetting::GOOGLE_SHEET_URL_KEY),
                'app_name' => AppSetting::getValue(AppSetting::APP_NAME_KEY),
                'app_logo' => ($path = AppSetting::getValue(AppSetting::APP_LOGO_KEY)) ? Storage::url($path) : null,
            ],
        ]);
    }

    /**
     * Update the application settings.
     */
    public function update(AppSettingsUpdateRequest $request): RedirectResponse
    {
        AppSetting::setValue(
            AppSetting::GOOGLE_SHEET_URL_KEY,
            $request->validated('google_sheet_url')
        );

        // app name
        AppSetting::setValue(
            AppSetting::APP_NAME_KEY,
            $request->validated('app_name')
        );

        // app logo upload
        if ($request->hasFile('app_logo')) {
            $path = $request->file('app_logo')->store('app_logos', 'public');
            AppSetting::setValue(AppSetting::APP_LOGO_KEY, $path);
        }

        return back();
    }
}
