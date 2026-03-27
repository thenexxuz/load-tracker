<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AppSettingsUpdateRequest;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

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

        return back();
    }
}
